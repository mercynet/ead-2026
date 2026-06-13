<?php

namespace App\Support\DependencyAudit;

final readonly class Finding
{
    public string $fingerprint;

    public function __construct(
        public string $rule,
        public Severity $severity,
        public string $package,
        public string $version,
        public string $file,
        public string $evidence,
        public string $recommendation,
    ) {
        $this->fingerprint = sha1(implode('|', [
            $this->rule,
            $this->severity->label(),
            $this->package,
            $this->version,
            $this->file,
            $this->evidence,
        ]));
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'rule' => $this->rule,
            'severity' => $this->severity->label(),
            'package' => $this->package,
            'version' => $this->version,
            'file' => $this->file,
            'evidence' => $this->evidence,
            'recommendation' => $this->recommendation,
            'fingerprint' => $this->fingerprint,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function toBaselineEntry(?string $owner = null, ?string $reason = null, ?string $expiresAt = null): array
    {
        return [
            'fingerprint' => $this->fingerprint,
            'rule' => $this->rule,
            'package' => $this->package,
            'reason' => $reason ?? 'Generated baseline',
            'owner' => $owner ?? 'unknown',
            'expires_at' => $expiresAt ?? now()->addDays(90)->toDateString(),
        ];
    }
}
