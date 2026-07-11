<?php

namespace App\Console\Commands;

use App\Support\DependencyAudit\AuditReport;
use App\Support\DependencyAudit\DependencyAuditService;
use App\Support\DependencyAudit\Finding;
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
        {--update-baseline : Alias of --generate-baseline}
        {--all : Expand every finding, including non-blocking signals below the threshold}';

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

        $threshold = $this->failureThreshold();

        $this->render($report, $threshold);

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

    private function render(AuditReport $report, Severity $threshold): void
    {
        if ($this->option('format') === 'json') {
            $this->line(json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return;
        }

        $scope = $report->scannedVendor
            ? 'composer.json + composer.lock + vendor'
            : 'composer.json + composer.lock';

        $blocking = array_values(array_filter(
            $report->findings,
            fn (Finding $finding): bool => $finding->severity->meetsOrExceeds($threshold),
        ));
        $belowThreshold = array_values(array_filter(
            $report->findings,
            fn (Finding $finding): bool => ! $finding->severity->meetsOrExceeds($threshold),
        ));

        [$status, $statusClass, $headline, $headlineClass] = match (true) {
            $report->findings === [] => ['PASS', 'bg-green-600 text-black', 'Nenhum sinal de risco. Seguro para commitar.', 'text-green-400'],
            $blocking !== [] => ['FAIL', 'bg-red-600 text-white', count($blocking).' problema(s) bloqueante(s) — NÃO commitar antes de resolver.', 'text-red-400'],
            default => ['REVIEW', 'bg-yellow-500 text-black', count($belowThreshold).' sinal(is) abaixo do limite — não bloqueia; revise quando der.', 'text-yellow-400'],
        };

        $activeCount = $this->formatCount(count($report->findings));
        $suppressedCount = $this->formatCount(count($report->suppressed));
        $thresholdLabel = strtoupper($threshold->label());

        render(<<<HTML
<div class="mx-1 my-1">
    <div>
        <span class="px-1 font-bold {$statusClass}"> {$status} </span>
        <span class="ml-1 font-bold text-cyan-400">Dependency Audit</span>
    </div>
    <div class="mt-1 {$headlineClass}">{$headline}</div>
    <div class="mt-1 text-gray-500">Root {$report->rootPath}</div>
    <div class="text-gray-500">Scope {$scope}  ·  fail-on {$thresholdLabel}  ·  {$activeCount} ativo(s)  ·  {$suppressedCount} suprimido(s)</div>
</div>
HTML);

        if ($report->findings === []) {
            return;
        }

        $showAll = (bool) $this->option('all');

        foreach (array_reverse(Severity::cases()) as $severity) {
            $rows = array_values(array_filter(
                $report->findings,
                fn (Finding $finding): bool => $finding->severity === $severity,
            ));

            if ($rows === []) {
                continue;
            }

            if (! $severity->meetsOrExceeds($threshold) && ! $showAll) {
                continue; // resumido em uma linha abaixo
            }

            $this->newLine();
            render(sprintf(
                '<div class="mx-1 mb-1 %s">%s · %d finding(s)</div>',
                $this->severityHeadingClasses($severity),
                strtoupper($severity->label()),
                count($rows),
            ));

            $this->table(
                ['Rule', 'Package', 'Version', 'File'],
                array_map(fn (Finding $finding): array => [
                    $finding->rule,
                    $finding->package,
                    $finding->version,
                    Str::limit($finding->file, 48),
                ], $rows),
            );

            foreach ($rows as $finding) {
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

        if (! $showAll && $belowThreshold !== []) {
            $this->newLine();
            render(sprintf(
                '<div class="mx-1">
                    <div class="text-gray-400">Abaixo do limite (%d, não bloqueia): <span class="text-white">%s</span></div>
                    <div class="text-gray-500">--all para detalhar · --generate-baseline para aceitar · --format=json para máquina</div>
                </div>',
                count($belowThreshold),
                $this->summariseByRule($belowThreshold),
            ));
        }
    }

    /**
     * @param  list<Finding>  $findings
     */
    private function summariseByRule(array $findings): string
    {
        return collect($findings)
            ->countBy(fn (Finding $finding): string => $finding->rule)
            ->sortDesc()
            ->map(fn (int $count, string $rule): string => "{$rule} ×{$count}")
            ->values()
            ->implode(', ');
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
