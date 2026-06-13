<?php

namespace App\Console\Commands;

use App\Support\DependencyAudit\AuditReport;
use App\Support\DependencyAudit\DependencyAuditService;
use App\Support\DependencyAudit\Severity;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use RuntimeException;

use function Termwind\render;

class SecurityAuditDepsCommand extends Command
{
    protected $signature = 'security:audit-deps
        {--path= : Root path to audit (defaults to repository base path)}
        {--lock-only : Audit only composer.json and composer.lock}
        {--scan-vendor : Also verify vendor consistency and scan autoload/bin entrypoints}
        {--include-dev : Include require-dev and packages-dev}
        {--format=pretty : pretty, table, or json}
        {--fail-on= : Override severity threshold}
        {--no-baseline : Ignore the configured baseline file}
        {--baseline= : Override baseline file path}
        {--generate-baseline : Write current findings to the baseline file and exit successfully}
        {--update-baseline : Alias of --generate-baseline}';

    protected $description = 'Audita dependências Composer em busca de sinais locais de supply-chain risk';

    public function __construct(private readonly DependencyAuditService $auditService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $report = $this->auditService->audit([
                'path' => $this->option('path') ?: base_path(),
                'include_dev' => (bool) $this->option('include-dev'),
                'scan_vendor' => $this->shouldScanVendor(),
                'no_baseline' => (bool) $this->option('no-baseline'),
                'baseline_path' => $this->option('baseline') ?: null,
            ]);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return 2;
        }

        if ((bool) $this->option('generate-baseline') || (bool) $this->option('update-baseline')) {
            return $this->writeBaseline($report);
        }

        $this->render($report);

        $threshold = $this->failureThreshold();

        return $report->highestSeverity()->meetsOrExceeds($threshold)
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function shouldScanVendor(): bool
    {
        if ((bool) $this->option('lock-only')) {
            return false;
        }

        return (bool) $this->option('scan-vendor');
    }

    private function failureThreshold(): Severity
    {
        $configured = (string) ($this->option('fail-on') ?: config('dependency_audit.fail_on', 'high'));

        return Severity::fromString($configured);
    }

    private function writeBaseline(AuditReport $report): int
    {
        $baselinePath = (string) ($this->option('baseline') ?: base_path(config('dependency_audit.baseline_path')));
        $directory = dirname($baselinePath);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents(
            $baselinePath,
            json_encode($this->auditService->makeBaselineEntries($report->findings), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
        );

        $this->components->info('Baseline written to '.$baselinePath);

        return self::SUCCESS;
    }

    private function render(AuditReport $report): void
    {
        if ($this->option('format') === 'json') {
            $this->line(json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return;
        }

        $scope = $report->scannedVendor
            ? 'composer.json + composer.lock + vendor'
            : 'composer.json + composer.lock';
        $status = $report->findings === [] ? 'PASS' : 'FAIL';
        $statusClass = $report->findings === [] ? 'bg-green-600 text-black' : 'bg-yellow-400 text-black';

        render(<<<HTML
<div class="mx-1 mb-1">
    <div class="mb-1">
        <span class="font-bold text-cyan-400">Dependency Audit</span>
        <span class="ml-2 px-1 {$statusClass}">{$status}</span>
    </div>
    <div class="text-gray-300">Root: <span class="text-white">{$report->rootPath}</span></div>
    <div class="text-gray-300">Scope: <span class="text-white">{$scope}</span></div>
    <div class="text-gray-300">Threshold summary: <span class="text-white">{$report->highestSeverity()->label()}</span> highest severity, <span class="text-white">{$this->formatCount(count($report->findings))}</span> active findings, <span class="text-white">{$this->formatCount(count($report->suppressed))}</span> suppressed</div>
</div>
HTML);

        $severityRows = collect(Severity::cases())
            ->map(fn (Severity $severity): array => [
                strtoupper($severity->label()),
                (string) ($report->countsBySeverity()[$severity->label()] ?? 0),
            ])
            ->filter(fn (array $row): bool => $row[1] !== '0')
            ->values()
            ->all();

        if ($severityRows !== []) {
            $this->newLine();
            $this->table(['Severity', 'Count'], $severityRows);
        }

        if ($report->findings === []) {
            render('<div class="mx-1 mt-1 text-green-400">No findings above the current baseline / threshold.</div>');

            return;
        }

        foreach (array_reverse(Severity::cases()) as $severity) {
            $findings = array_values(array_filter(
                $report->findings,
                fn ($finding): bool => $finding->severity === $severity,
            ));

            if ($findings === []) {
                continue;
            }

            $this->newLine();
            render(sprintf(
                '<div class="mx-1 mb-1 %s">%s · %s finding(s)</div>',
                $this->severityHeadingClasses($severity),
                strtoupper($severity->label()),
                count($findings),
            ));

            $this->table(
                ['Rule', 'Package', 'Version', 'File'],
                array_map(fn ($finding): array => [
                    $finding->rule,
                    $finding->package,
                    $finding->version,
                    Str::limit($finding->file, 48),
                ], $findings),
            );

            if (! $severity->meetsOrExceeds(Severity::High)) {
                continue;
            }

            foreach ($findings as $finding) {
                render(sprintf(
                    '<div class="mx-1 mb-1">
                        <div><span class="text-cyan-400">%s</span> <span class="text-white">%s %s</span></div>
                        <div class="text-gray-300">Evidence: <span class="text-white">%s</span></div>
                        <div class="text-gray-300">Recommendation: <span class="text-white">%s</span></div>
                        <div class="text-gray-500">Fingerprint: %s</div>
                    </div>',
                    $finding->rule,
                    $finding->package,
                    $finding->version,
                    htmlspecialchars($finding->evidence, ENT_QUOTES),
                    htmlspecialchars($finding->recommendation, ENT_QUOTES),
                    $finding->fingerprint,
                ));
            }
        }

        render('<div class="mx-1 mt-1 text-gray-500">Tip: use --format=json for machine output.</div>');
    }

    private function severityHeadingClasses(Severity $severity): string
    {
        return match ($severity) {
            Severity::Critical => 'font-bold text-red-400',
            Severity::High => 'font-bold text-yellow-400',
            Severity::Medium => 'font-bold text-cyan-400',
            Severity::Low => 'font-bold text-blue-400',
            Severity::Info => 'font-bold text-gray-400',
        };
    }

    private function formatCount(int $count): string
    {
        return number_format($count);
    }
}
