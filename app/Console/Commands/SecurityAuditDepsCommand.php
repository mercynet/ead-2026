<?php

namespace App\Console\Commands;

use App\Support\DependencyAudit\AuditReport;
use App\Support\DependencyAudit\DependencyAuditService;
use App\Support\DependencyAudit\Severity;
use Illuminate\Console\Command;
use RuntimeException;

class SecurityAuditDepsCommand extends Command
{
    protected $signature = 'security:audit-deps
        {--path= : Root path to audit (defaults to repository base path)}
        {--lock-only : Audit only composer.json and composer.lock}
        {--scan-vendor : Also verify vendor consistency and scan autoload/bin entrypoints}
        {--include-dev : Include require-dev and packages-dev}
        {--format=table : table or json}
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

        $this->components->info('Dependency Audit');
        $this->line('Root: '.$report->rootPath);
        $this->line('Scope: '.($report->scannedVendor ? 'composer.json + composer.lock + vendor' : 'composer.json + composer.lock'));
        $this->line('Findings: '.count($report->findings).' | Suppressed by baseline: '.count($report->suppressed));

        if ($report->findings === []) {
            $this->components->info('No findings above the current baseline/threshold.');

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
            $this->warn(strtoupper($severity->label()));

            foreach ($findings as $finding) {
                $this->line("[{$finding->rule}] {$finding->package} {$finding->version}");
                $this->line('  file: '.$finding->file);
                $this->line('  evidence: '.$finding->evidence);
                $this->line('  recommendation: '.$finding->recommendation);
                $this->line('  fingerprint: '.$finding->fingerprint);
            }
        }
    }
}
