<?php

namespace App\Support\DependencyAudit;

use Illuminate\Support\Collection;

final readonly class AuditReport
{
    /**
     * @param  list<Finding>  $findings
     * @param  list<Finding>  $suppressed
     */
    public function __construct(
        public array $findings,
        public array $suppressed,
        public string $rootPath,
        public bool $scannedVendor,
    ) {}

    public function highestSeverity(): Severity
    {
        if ($this->findings === []) {
            return Severity::Info;
        }

        return Collection::make($this->findings)
            ->map(fn (Finding $finding): Severity => $finding->severity)
            ->sortByDesc(fn (Severity $severity): int => $severity->value)
            ->first();
    }

    /**
     * @return array<string, int>
     */
    public function countsBySeverity(): array
    {
        return Collection::make(Severity::cases())
            ->mapWithKeys(fn (Severity $severity): array => [
                $severity->label() => Collection::make($this->findings)
                    ->where(fn (Finding $finding): bool => $finding->severity === $severity)
                    ->count(),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'root_path' => $this->rootPath,
            'scanned_vendor' => $this->scannedVendor,
            'highest_severity' => $this->highestSeverity()->label(),
            'counts' => $this->countsBySeverity(),
            'suppressed' => count($this->suppressed),
            'findings' => array_map(fn (Finding $finding): array => $finding->toArray(), $this->findings),
        ];
    }
}
