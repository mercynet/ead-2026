#!/usr/bin/env python3
"""Read-only validator for repository AI harness configuration.

Frontmatter parser accepts top-level scalar ``key: value`` pairs plus ``|`` and
``>`` block scalars with indented content. Nested simple YAML is tolerated for
additional metadata, but is not parsed as a list or object.
"""

from __future__ import annotations

import argparse
import json
import os
import re
import sys
import tomllib
from pathlib import Path
from typing import Any


ROOT = Path(__file__).resolve().parents[2]
SKILLS_DIR = ROOT / ".agents" / "skills"
ROUTING_MAP = SKILLS_DIR / "routing.json"
ROUTER_SCRIPT = ROOT / "scripts" / "ai" / "skill-router.sh"
CAPABILITY_MANIFEST = ROOT / ".agents" / "capability-context.json"
CAPABILITY_CONTEXT_RESOLVER = ROOT / "scripts" / "ai" / "capability-context.sh"
REQUIRED_JSON_CONFIGS = (Path(".mcp.json"), Path(".claude/settings.json"), Path("opencode.json"))
OPTIONAL_JSON_CONFIGS = (Path(".codex/hooks.json"), Path(".opencode/opencode.json"))
TOML_CONFIG = Path(".codex/config.toml")
SKILL_NAME = re.compile(r"^[a-z0-9]+(?:-[a-z0-9]+)*$")
POSIX_ABSOLUTE_PATH = re.compile(r"(?<![A-Za-z0-9_:/])/(?!/)[^\s'\"`<>|;(){}\[\],]+")
WINDOWS_ABSOLUTE_PATH = re.compile(r"(?<![A-Za-z0-9_])[A-Za-z]:[\\/][^\s'\"`<>|;(){}\[\],]*")
UNC_ABSOLUTE_PATH = re.compile(r"\\\\[^\\/\s]+[\\/][^\s'\"`<>|;(){}\[\],]*")
FREE_SECRET_PATTERNS = (
    re.compile(r"\b(?:ghp|gho|ghu|ghs|github_pat)_[A-Za-z0-9_]{20,}\b"),
    re.compile(r"\bsk-(?:proj-)?[A-Za-z0-9_-]{20,}\b"),
    re.compile(r"-----BEGIN (?:[A-Z ]+ )?PRIVATE KEY-----"),
    re.compile(r"\b(?:AKIA|ASIA)[A-Z0-9]{16}\b"),
)
SENSITIVE_KEY = re.compile(r"(?i)(?:password|secret|token|api[_-]?key|access[_-]?key|private[_-]?key)")
ENV_REFERENCE = re.compile(r"(?:\$\{?[A-Za-z_][A-Za-z0-9_]*\}?|\benv\s*\()", re.IGNORECASE)
READ_ONLY = re.compile(r"(?:github[_-]?read[_-]?only\s*=\s*(?:1|true)|read[_-]?only\s*[:=]\s*(?:1|true)|--read-only)", re.IGNORECASE)
GH_AUTH_TOKEN = re.compile(r"\bgh\s+auth\s+token\b", re.IGNORECASE)
GITHUB_TOKEN_ASSIGNMENT = re.compile(
    r"(?:^|\s)(?:GITHUB_PERSONAL_ACCESS_TOKEN|GITHUB_TOKEN|GH_TOKEN)\s*=\s*", re.IGNORECASE
)
RUNTIME_TOKEN_REFERENCE = re.compile(
    r"(?:\$\(\s*gh\s+auth\s+token\s*\)|\$\{?(?:ENV|[A-Za-z_][A-Za-z0-9_]*(?:TOKEN|SECRET|API_KEY))\}?|\{env:(?:ENV|[A-Za-z_][A-Za-z0-9_]*(?:TOKEN|SECRET|API_KEY))\})",
    re.IGNORECASE,
)
ALLOWED_ABSOLUTE_PATHS = {"/var/www/html/artisan", "/dev/null"}


class Reporter:
    def __init__(self) -> None:
        self.failures = 0
        self.warnings = 0

    def emit(self, level: str, message: str) -> None:
        print(f"{level}: {message}")
        if level == "FAIL":
            self.failures += 1
        elif level == "WARN":
            self.warnings += 1

    def pass_(self, message: str) -> None:
        self.emit("PASS", message)

    def fail(self, message: str) -> None:
        self.emit("FAIL", message)

    def warn(self, message: str) -> None:
        self.emit("WARN", message)


def relative(path: Path) -> str:
    return path.relative_to(ROOT).as_posix()


def location(content: str, offset: int) -> str:
    return f"line {content.count(chr(10), 0, offset) + 1}, column {offset - content.rfind(chr(10), 0, offset)}"


def report_at(reporter: Reporter, level: str, path: Path, content: str, offset: int, category: str) -> None:
    reporter.emit(level, f"{relative(path)}: {category} at {location(content, offset)} (value redacted)")


def read_text(path: Path, reporter: Reporter) -> str | None:
    try:
        return path.read_text(encoding="utf-8")
    except (OSError, UnicodeDecodeError) as error:
        reporter.fail(f"{relative(path)} cannot be read as UTF-8: {error}")
        return None


def validate_skills(reporter: Reporter) -> None:
    if not SKILLS_DIR.is_dir():
        reporter.fail(".agents/skills directory is missing")
        return
    reporter.pass_(".agents/skills exists")
    skills = sorted(path for path in SKILLS_DIR.iterdir() if path.is_dir())
    if not skills:
        reporter.warn(".agents/skills contains no skill directories")
    for skill in skills:
        validate_skill(skill, reporter)


def validate_skill(skill: Path, reporter: Reporter) -> None:
    document = skill / "SKILL.md"
    if not document.is_file():
        reporter.fail(f"{relative(skill)} is missing SKILL.md")
        return
    content = read_text(document, reporter)
    if content is None:
        return
    metadata = parse_frontmatter(content, document, reporter)
    if metadata is None:
        return
    name = metadata.get("name")
    description = metadata.get("description")
    if not name:
        reporter.fail(f"{relative(document)} frontmatter is missing name")
    elif name != skill.name:
        reporter.fail(f"{relative(document)} name does not match directory")
    elif not SKILL_NAME.fullmatch(name):
        reporter.fail(f"{relative(document)} name must use lowercase-hyphen format")
    if not description:
        reporter.fail(f"{relative(document)} frontmatter is missing description")
    if name == skill.name and SKILL_NAME.fullmatch(name or "") and description:
        reporter.pass_(f"{relative(document)} frontmatter is valid")


def parse_frontmatter(content: str, path: Path, reporter: Reporter) -> dict[str, str] | None:
    lines = content.splitlines()
    if not lines or lines[0] != "---":
        reporter.fail(f"{relative(path)} must start with --- frontmatter delimiter")
        return None
    try:
        end = lines.index("---", 1)
    except ValueError:
        reporter.fail(f"{relative(path)} frontmatter has no closing --- delimiter")
        return None

    metadata: dict[str, str] = {}
    block_key: str | None = None
    block_style: str | None = None
    block_lines: list[str] = []
    for line_number, line in enumerate(lines[1:end], start=2):
        if line and line[0].isspace():
            if block_key is not None:
                block_lines.append(line.strip())
            continue
        if block_key is not None:
            separator = "\n" if block_style == "|" else " "
            metadata[block_key] = separator.join(part for part in block_lines if part).strip()
            block_key = None
            block_style = None
            block_lines = []
        if not line.strip() or line.lstrip().startswith("#"):
            continue
        match = re.fullmatch(r"([A-Za-z][A-Za-z0-9_-]*):\s*(.*?)\s*", line)
        if match is None:
            reporter.fail(f"{relative(path)} has unsupported simple YAML on line {line_number}")
            return None
        key, value = match.groups()
        if key in metadata:
            reporter.fail(f"{relative(path)} repeats frontmatter key '{key}'")
            return None
        if value in {"|", ">"}:
            block_key, block_style = key, value
            continue
        metadata[key] = value.strip().strip("'\"")
    if block_key is not None:
        separator = "\n" if block_style == "|" else " "
        metadata[block_key] = separator.join(part for part in block_lines if part).strip()
    return metadata


def validate_skill_links(reporter: Reporter) -> None:
    for adapter in (".claude", ".codex", ".opencode"):
        link = ROOT / adapter / "skills"
        if not link.is_symlink():
            reporter.fail(f"{relative(link)} must be a symlink to .agents/skills")
            continue
        try:
            resolved = link.resolve(strict=True)
        except OSError:
            reporter.fail(f"{relative(link)} is broken")
            continue
        if resolved != SKILLS_DIR.resolve():
            reporter.fail(f"{relative(link)} does not resolve to .agents/skills")
            continue
        reporter.pass_(f"{relative(link)} resolves to .agents/skills")


def validate_skill_routing(reporter: Reporter) -> None:
    """Every skill must be auto-routed or explicitly opted out, and every rule must compile."""
    if not ROUTING_MAP.is_file():
        reporter.fail(f"{relative(ROUTING_MAP)} is missing (skill auto-routing map)")
        return
    content = read_text(ROUTING_MAP, reporter)
    if content is None:
        return
    try:
        routing = json.loads(content)
    except json.JSONDecodeError as error:
        reporter.fail(f"{relative(ROUTING_MAP)} invalid JSON at line {error.lineno}, column {error.colno}")
        return
    if not isinstance(routing, dict):
        reporter.fail(f"{relative(ROUTING_MAP)} must be a JSON object")
        return

    rules = routing.get("rules")
    manual = routing.get("manual", [])
    if not isinstance(rules, list) or not isinstance(manual, list):
        reporter.fail(f"{relative(ROUTING_MAP)} requires a rules list and a manual list")
        return

    routed: set[str] = set()
    for index, rule in enumerate(rules):
        if not isinstance(rule, dict):
            reporter.fail(f"{relative(ROUTING_MAP)} rule #{index} is not an object")
            continue
        skill = rule.get("skill")
        if not isinstance(skill, str) or not skill:
            reporter.fail(f"{relative(ROUTING_MAP)} rule #{index} is missing skill")
            continue
        if not (SKILLS_DIR / skill / "SKILL.md").is_file():
            reporter.fail(f"{relative(ROUTING_MAP)} routes unknown skill '{skill}'")
            continue
        if not isinstance(rule.get("why"), str) or not rule["why"]:
            reporter.fail(f"{relative(ROUTING_MAP)} rule for '{skill}' is missing why")
            continue
        triggers = 0
        for field in ("paths", "prompt"):
            patterns = rule.get(field, [])
            if not isinstance(patterns, list):
                reporter.fail(f"{relative(ROUTING_MAP)} '{skill}' field {field} must be a list")
                continue
            triggers += len(patterns)
            for pattern in patterns:
                try:
                    re.compile(pattern)
                except re.error as error:
                    reporter.fail(f"{relative(ROUTING_MAP)} '{skill}' {field} pattern does not compile: {error}")
        if triggers == 0:
            reporter.fail(f"{relative(ROUTING_MAP)} '{skill}' has no paths/prompt trigger")
            continue
        routed.add(skill)

    for skill in manual:
        if not isinstance(skill, str) or not (SKILLS_DIR / skill / "SKILL.md").is_file():
            reporter.fail(f"{relative(ROUTING_MAP)} manual list references unknown skill '{skill}'")

    available = {path.name for path in SKILLS_DIR.iterdir() if path.is_dir()} if SKILLS_DIR.is_dir() else set()
    unrouted = sorted(available - routed - {skill for skill in manual if isinstance(skill, str)})
    if unrouted:
        reporter.fail(
            f"{relative(ROUTING_MAP)} does not cover: {', '.join(unrouted)} (add a rule or list under manual)"
        )
        return

    if not ROUTER_SCRIPT.is_file() or not os.access(ROUTER_SCRIPT, os.X_OK):
        reporter.fail(f"{relative(ROUTER_SCRIPT)} is missing or not executable")
        return

    context_manifest = routing.get("context_manifest")
    if not isinstance(context_manifest, dict):
        reporter.fail(f"{relative(ROUTING_MAP)} is missing context_manifest")
    elif context_manifest.get("path") != ".agents/capability-context.json":
        reporter.fail(f"{relative(ROUTING_MAP)} context_manifest.path must point to .agents/capability-context.json")
    elif context_manifest.get("resolver") != "scripts/ai/capability-context.sh":
        reporter.fail(f"{relative(ROUTING_MAP)} context_manifest.resolver must point to scripts/ai/capability-context.sh")
    elif context_manifest.get("mode") != "advisory":
        reporter.fail(f"{relative(ROUTING_MAP)} context_manifest.mode must be advisory")
    else:
        reporter.pass_(f"{relative(ROUTING_MAP)} declares the capability context resolver")

    reporter.pass_(f"{relative(ROUTING_MAP)} routes every skill ({len(routed)} auto, {len(manual)} manual)")


def validate_capability_context(reporter: Reporter) -> None:
    """Validate the declarative business-context bundles used by the skill router."""
    if not CAPABILITY_MANIFEST.is_file():
        reporter.fail(f"{relative(CAPABILITY_MANIFEST)} is missing (capability context manifest)")
        return

    content = read_text(CAPABILITY_MANIFEST, reporter)
    if content is None:
        return
    try:
        manifest = json.loads(content)
    except json.JSONDecodeError as error:
        reporter.fail(
            f"{relative(CAPABILITY_MANIFEST)} invalid JSON at line {error.lineno}, column {error.colno}"
        )
        return

    if not isinstance(manifest, dict) or manifest.get("version") != 1:
        reporter.fail(f"{relative(CAPABILITY_MANIFEST)} must be a version 1 object")
        return

    bundles = manifest.get("bundles")
    if not isinstance(bundles, list):
        reporter.fail(f"{relative(CAPABILITY_MANIFEST)} requires a bundles list")
        return

    expected = {"categories", "learning-course-content", "assessment", "ecosystem-plugins", "financial"}
    found: set[str] = set()
    for index, bundle in enumerate(bundles):
        if not isinstance(bundle, dict):
            reporter.fail(f"{relative(CAPABILITY_MANIFEST)} bundle #{index} is not an object")
            continue
        bundle_id = bundle.get("id")
        if not isinstance(bundle_id, str) or not bundle_id:
            reporter.fail(f"{relative(CAPABILITY_MANIFEST)} bundle #{index} is missing id")
            continue
        if bundle_id in found:
            reporter.fail(f"{relative(CAPABILITY_MANIFEST)} repeats bundle '{bundle_id}'")
        found.add(bundle_id)
        for key in ("domain", "summary", "match", "personas", "specs", "skills", "rules", "invariants", "tests", "classification", "decision_gates"):
            if key not in bundle:
                reporter.fail(f"{relative(CAPABILITY_MANIFEST)} bundle '{bundle_id}' is missing {key}")

        match = bundle.get("match", {})
        if not isinstance(match, dict):
            reporter.fail(f"{relative(CAPABILITY_MANIFEST)} bundle '{bundle_id}' match must be an object")
        else:
            for field in ("prompt", "paths"):
                patterns = match.get(field, [])
                if not isinstance(patterns, list) or not patterns:
                    reporter.fail(f"{relative(CAPABILITY_MANIFEST)} bundle '{bundle_id}' match.{field} must be a non-empty list")
                    continue
                for pattern in patterns:
                    if not isinstance(pattern, str):
                        reporter.fail(f"{relative(CAPABILITY_MANIFEST)} bundle '{bundle_id}' has non-string {field} pattern")
                        continue
                    try:
                        re.compile(pattern)
                    except re.error as error:
                        reporter.fail(f"{relative(CAPABILITY_MANIFEST)} bundle '{bundle_id}' {field} pattern does not compile: {error}")

        for field in ("personas", "specs", "skills", "rules", "invariants", "tests"):
            values = bundle.get(field)
            if not isinstance(values, list) or not values:
                reporter.fail(f"{relative(CAPABILITY_MANIFEST)} bundle '{bundle_id}' {field} must be a non-empty list")
                continue
            for value in values:
                if not isinstance(value, str) or not value:
                    reporter.fail(f"{relative(CAPABILITY_MANIFEST)} bundle '{bundle_id}' {field} has a non-string value")
                    continue
                if field in {"specs", "tests"} and not (ROOT / value).is_file():
                    reporter.fail(f"{relative(CAPABILITY_MANIFEST)} bundle '{bundle_id}' references missing {field[:-1]} '{value}'")
                if field == "skills" and not (SKILLS_DIR / value / "SKILL.md").is_file():
                    reporter.fail(f"{relative(CAPABILITY_MANIFEST)} bundle '{bundle_id}' references unknown skill '{value}'")

        classification = bundle.get("classification")
        if not isinstance(classification, dict) or not classification.get("layer") or not classification.get("core") or not classification.get("plugin"):
            reporter.fail(f"{relative(CAPABILITY_MANIFEST)} bundle '{bundle_id}' classification must declare layer, core and plugin")

        gates = bundle.get("decision_gates")
        if not isinstance(gates, list):
            reporter.fail(f"{relative(CAPABILITY_MANIFEST)} bundle '{bundle_id}' decision_gates must be a list")
            continue
        for gate in gates:
            if not isinstance(gate, dict) or gate.get("status") != "HUMAN_DECISION_REQUIRED":
                reporter.fail(f"{relative(CAPABILITY_MANIFEST)} bundle '{bundle_id}' has a decision gate without HUMAN_DECISION_REQUIRED")
                continue
            gate_match = gate.get("match", {})
            if not isinstance(gate_match, dict):
                reporter.fail(f"{relative(CAPABILITY_MANIFEST)} bundle '{bundle_id}' gate match must be an object")
                continue
            for field in ("prompt", "paths"):
                for pattern in gate_match.get(field, []):
                    if not isinstance(pattern, str):
                        reporter.fail(f"{relative(CAPABILITY_MANIFEST)} bundle '{bundle_id}' gate has non-string {field} pattern")
                        continue
                    try:
                        re.compile(pattern)
                    except re.error as error:
                        reporter.fail(f"{relative(CAPABILITY_MANIFEST)} bundle '{bundle_id}' gate {field} pattern does not compile: {error}")

    missing = sorted(expected - found)
    if missing:
        reporter.fail(f"{relative(CAPABILITY_MANIFEST)} is missing required bundles: {', '.join(missing)}")
    if not CAPABILITY_CONTEXT_RESOLVER.is_file() or not os.access(CAPABILITY_CONTEXT_RESOLVER, os.X_OK):
        reporter.fail(f"{relative(CAPABILITY_CONTEXT_RESOLVER)} is missing or not executable")
    else:
        reporter.pass_(f"{relative(CAPABILITY_MANIFEST)} validates {len(bundles)} capability bundles")


def validate_configs(reporter: Reporter) -> dict[Path, tuple[Any, str]]:
    configs: dict[Path, tuple[Any, str]] = {}
    for config in REQUIRED_JSON_CONFIGS:
        parse_json_config(config, True, reporter, configs)
    for config in OPTIONAL_JSON_CONFIGS:
        parse_json_config(config, False, reporter, configs)
    parse_toml_config(reporter, configs)
    return configs


def parse_json_config(config: Path, required: bool, reporter: Reporter, configs: dict[Path, tuple[Any, str]]) -> None:
    path = ROOT / config
    if not path.is_file():
        (reporter.fail if required else reporter.warn)(f"{config.as_posix()} is missing")
        return
    content = read_text(path, reporter)
    if content is None:
        return
    try:
        configs[path] = (json.loads(content), content)
    except json.JSONDecodeError as error:
        reporter.fail(f"{config.as_posix()} invalid JSON at line {error.lineno}, column {error.colno}")
    else:
        reporter.pass_(f"{config.as_posix()} JSON is valid")


def parse_toml_config(reporter: Reporter, configs: dict[Path, tuple[Any, str]]) -> None:
    path = ROOT / TOML_CONFIG
    if not path.is_file():
        reporter.fail(f"{TOML_CONFIG.as_posix()} is missing")
        return
    content = read_text(path, reporter)
    if content is None:
        return
    try:
        configs[path] = (tomllib.loads(content), content)
    except tomllib.TOMLDecodeError as error:
        reporter.fail(f"{TOML_CONFIG.as_posix()} invalid TOML: {error}")
    else:
        reporter.pass_(f"{TOML_CONFIG.as_posix()} TOML is valid")


def validate_executables(reporter: Reporter) -> None:
    scripts = sorted((ROOT / ".githooks").glob("*")) + sorted((ROOT / "scripts" / "ai").glob("*.sh"))
    for script in scripts:
        if script.is_file():
            if os.access(script, os.X_OK):
                reporter.pass_(f"{relative(script)} is executable")
            else:
                reporter.fail(f"{relative(script)} must be executable")


def iter_items(value: Any, key: str | None = None):
    if isinstance(value, dict):
        for child_key, child_value in value.items():
            yield from iter_items(child_value, str(child_key))
    elif isinstance(value, list):
        for child in value:
            yield from iter_items(child, key)
    else:
        yield key, value


def is_runtime_reference(value: str) -> bool:
    return bool(ENV_REFERENCE.search(value) or GH_AUTH_TOKEN.search(value))


def find_offset(content: str, needle: str) -> int:
    return max(content.find(needle), 0)


def is_allowed_absolute_path(value: str) -> bool:
    return value in ALLOWED_ABSOLUTE_PATHS


def validate_sensitive_values(configs: dict[Path, tuple[Any, str]], reporter: Reporter) -> None:
    for path, (data, content) in configs.items():
        path_reported = False
        secret_reported = False
        for pattern in (POSIX_ABSOLUTE_PATH, WINDOWS_ABSOLUTE_PATH, UNC_ABSOLUTE_PATH):
            match = pattern.search(content)
            if match and not is_allowed_absolute_path(match.group(0)):
                report_at(reporter, "FAIL", path, content, match.start(), "machine-specific absolute path")
                path_reported = True
                break
        for pattern in FREE_SECRET_PATTERNS:
            match = pattern.search(content)
            if match:
                report_at(reporter, "FAIL", path, content, match.start(), "apparent literal credential")
                secret_reported = True
                break
        if secret_reported:
            continue
        for key, value in iter_items(data):
            if isinstance(value, str) and key and SENSITIVE_KEY.search(key) and not is_runtime_reference(value):
                report_at(reporter, "FAIL", path, content, find_offset(content, key), "literal value for sensitive key")
                break
        if path_reported:
            continue


def mcp_servers(data: Any, config: Path) -> dict[str, Any] | None:
    if not isinstance(data, dict):
        return None
    if config == Path(".mcp.json"):
        servers = data.get("mcpServers")
    elif config == TOML_CONFIG:
        servers = data.get("mcp_servers")
    else:
        servers = data.get("mcp")
    return servers if isinstance(servers, dict) else None


def validate_mcp_invariants(configs: dict[Path, tuple[Any, str]], reporter: Reporter) -> None:
    for config in (Path(".mcp.json"), TOML_CONFIG, Path("opencode.json")):
        path = ROOT / config
        entry = configs.get(path)
        if entry is None:
            continue
        servers = mcp_servers(entry[0], config)
        if servers is None:
            reporter.fail(f"{config.as_posix()} has no MCP server map")
            continue
        for server in ("laravel-boost", "github"):
            if server not in servers:
                reporter.fail(f"{config.as_posix()} MCP is missing {server}")
        github = servers.get("github")
        github_text = " ".join(stringify(github))
        if github is not None and not READ_ONLY.search(github_text):
            reporter.fail(f"{config.as_posix()} GitHub MCP must use read-only mode")
        if github is not None:
            token_assignment = github_token_assignment(github_text)
            if token_assignment is None:
                reporter.fail(f"{config.as_posix()} GitHub MCP must assign a runtime-derived GitHub token")
            elif not RUNTIME_TOKEN_REFERENCE.match(token_assignment):
                reporter.fail(f"{config.as_posix()} GitHub MCP has a literal GitHub token assignment (value redacted)")


def stringify(value: Any) -> list[str]:
    if isinstance(value, dict):
        return [part for child in value.values() for part in stringify(child)]
    if isinstance(value, list):
        return [part for child in value for part in stringify(child)]
    return [value] if isinstance(value, str) else []


def github_token_assignment(command: str) -> str | None:
    """Return assignment value prefix, or None when no supported assignment exists."""
    match = GITHUB_TOKEN_ASSIGNMENT.search(command)
    if match is None:
        return None
    value = command[match.end() :].lstrip("'\"")
    return value


def main() -> int:
    parser = argparse.ArgumentParser(description="Validate read-only AI harness configuration.")
    parser.parse_args()
    reporter = Reporter()
    validate_skills(reporter)
    validate_skill_links(reporter)
    validate_skill_routing(reporter)
    validate_capability_context(reporter)
    configs = validate_configs(reporter)
    validate_executables(reporter)
    validate_sensitive_values(configs, reporter)
    validate_mcp_invariants(configs, reporter)
    if reporter.failures:
        print(f"FAIL: {reporter.failures} failure(s), {reporter.warnings} warning(s)")
        return 1
    print(f"PASS: harness validation passed with {reporter.warnings} warning(s)")
    return 0


if __name__ == "__main__":
    sys.exit(main())
