<?php

namespace App\Console\Commands;

use App\Modules\Core\Enums\UserType;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * Runner E2E: executa um spec declarativo (tests/e2e-http/<spec>.php) contra o app
 * RODANDO, batendo HTTP real e conferindo side effects direto no banco via Eloquent.
 *
 * Cria fixtures efêmeras (tenants + users por papel + tokens Sanctum), roda os casos
 * e limpa tudo no fim (a menos de --keep). Nunca roda em produção.
 *
 * Segurança (auditoria 2026-07-16): só roda em local|testing|e2e; recusa DB que não
 * pareça descartável (nome sem e2e/test) salvo --force-db; canário prova servidor↔DB
 * antes de qualquer mutação; timeout por request; circuit breaker no 5xx inesperado;
 * saída sanitizada (sem token/segredo).
 *
 * Stack e2e dedicada (recomendado): copie .env.e2e.example → .env.e2e (DB_DATABASE=ead2026_e2e,
 * APP_ENV=e2e, APP_DEBUG=false), suba a app com esse env e rode:
 *   php artisan e2e:run <spec> --base=http://localhost --fresh
 */
class E2eRunCommand extends Command
{
    protected $signature = 'e2e:run {spec : Caminho do spec relativo a tests/e2e-http (ex.: learning/courses-store)}
        {--base= : Base URL do app rodando (default: config app.url). Dentro do container Sail use --base=http://localhost (a porta publicada no host, ex. 8099, não é acessível de dentro do container)}
        {--timeout=10 : Timeout por request em segundos (evita travar num endpoint pendurado)}
        {--continue-on-error : Não abortar no primeiro 5xx inesperado (por padrão a corrida para)}
        {--fresh : Roda migrate:fresh no DB atual antes da bateria (só após o gate de DB descartável)}
        {--force-db : Permite rodar contra um DB que não parece descartável (dev). Use com cuidado}
        {--keep : Não limpar as fixtures efêmeras no fim (debug)}';

    protected $description = 'Executa um spec E2E contra o app rodando (HTTP real + asserts de DB)';

    /** @var array<string, mixed> */
    private array $ctx = [];

    private int $passed = 0;

    private int $failed = 0;

    private bool $aborted = false;

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing', 'e2e'])) {
            $this->error('e2e:run só roda em local|testing|e2e (ambiente atual: '.app()->environment().').');

            return self::FAILURE;
        }

        $spec = $this->loadSpec((string) $this->argument('spec'));
        if ($spec === null) {
            return self::FAILURE;
        }

        $database = (string) DB::connection()->getDatabaseName();
        if (! $this->isDisposableDatabase($database) && ! $this->option('force-db')) {
            $this->error("e2e:run recusa mutar o DB '{$database}' — não parece descartável.");
            $this->line('  Aponte a app+runner para um DB e2e (APP_ENV=e2e, DB_DATABASE=..._e2e) ou passe --force-db.');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $this->components->task("migrate:fresh em {$database}", fn (): bool => $this->callSilent('migrate:fresh', ['--force' => true]) === self::SUCCESS);
        }

        $base = rtrim((string) ($this->option('base') ?: config('app.url')), '/');
        [$method, $specPath] = $this->parseEndpoint($spec['endpoint']);

        $this->components->info("E2E: {$spec['endpoint']}  →  {$base}");

        try {
            $this->bootFixtures();

            if (! $this->canaryServerDbAligned($base)) {
                $this->error("Canário falhou: a app em {$base} não vê as fixtures do runner (DB '{$database}').");
                $this->line('  App fora do ar ou servida em outro DB — nenhuma mutação executada.');
                $this->failed++;
            } else {
                if (isset($spec['setup']) && is_callable($spec['setup'])) {
                    $this->ctx['fixtures'] = (array) $spec['setup']($this->ctx);
                }

                foreach ($spec['cases'] as $case) {
                    $this->runCase($base, $method, $specPath, $case);

                    if ($this->aborted) {
                        $this->error('Abortado no primeiro 5xx inesperado (use --continue-on-error para prosseguir).');
                        break;
                    }
                }
            }
        } catch (Throwable $e) {
            $this->error('Erro no runner: '.$this->sanitize($e->getMessage()));
            $this->failed++;
        } finally {
            if (isset($spec['cleanup']) && is_callable($spec['cleanup'])) {
                try {
                    $spec['cleanup']($this->ctx);
                } catch (Throwable $e) {
                    // Resíduo não removido é falha do runner: sem isto o exit code
                    // seria 0 mesmo deixando fixtures no banco (falso sucesso).
                    $this->error('cleanup do spec FALHOU (pode ter deixado resíduo): '.$this->sanitize($e->getMessage()));
                    $this->failed++;
                }
            }
            if ($this->option('keep')) {
                $this->warn('--keep: fixtures efêmeras mantidas no banco.');
            } else {
                $this->teardownFixtures();
            }
        }

        $this->newLine();
        $this->components->info("Resultado: {$this->passed} passou, {$this->failed} falhou.");

        return $this->failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array{endpoint: string, cases: array<int, array<string, mixed>>, setup?: callable, cleanup?: callable}|null
     */
    private function loadSpec(string $spec): ?array
    {
        $relative = str_ends_with($spec, '.php') ? $spec : $spec.'.php';
        $path = base_path('tests/e2e-http/'.$relative);

        if (! is_file($path)) {
            $this->error("Spec não encontrado: {$path}");

            return null;
        }

        $loaded = require $path;

        if (! is_array($loaded) || ! isset($loaded['endpoint'], $loaded['cases'])) {
            $this->error('Spec inválido: precisa retornar array com chaves endpoint e cases.');

            return null;
        }

        return $loaded;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parseEndpoint(string $endpoint): array
    {
        $parts = preg_split('/\s+/', trim($endpoint), 2);
        $method = strtolower($parts[0] ?? 'get');
        $path = $parts[1] ?? '/';

        return [$method, $path];
    }

    private function bootFixtures(): void
    {
        if (! Role::query()->where('name', 'admin')->exists()) {
            $this->components->task('seed permissions + roles', function (): void {
                (new PermissionsSeeder)->run();
                (new RolesSeeder)->run();
            });
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $suffix = bin2hex(random_bytes(8));

        $primary = Tenant::query()->create([
            'name' => "E2E Primary {$suffix}",
            'domain' => "e2e-primary-{$suffix}.local",
            'database' => null,
            'is_active' => true,
        ]);

        $other = Tenant::query()->create([
            'name' => "E2E Other {$suffix}",
            'domain' => "e2e-other-{$suffix}.local",
            'database' => null,
            'is_active' => true,
        ]);

        $users = [
            'admin' => $this->makeUser($primary->id, UserType::Admin, 'admin', "e2e-admin-{$suffix}@test.local"),
            'instructor' => $this->makeUser($primary->id, UserType::Instructor, 'instructor', "e2e-instructor-{$suffix}@test.local"),
            'student' => $this->makeUser($primary->id, UserType::Student, 'student', "e2e-student-{$suffix}@test.local"),
            'developer' => $this->makeUser(null, UserType::Developer, 'developer', "e2e-dev-{$suffix}@test.local"),
            'otherAdmin' => $this->makeUser($other->id, UserType::Admin, 'admin', "e2e-other-admin-{$suffix}@test.local"),
        ];

        $tokens = [];
        foreach ($users as $key => $user) {
            $tokens[$key] = $user->createToken("e2e-{$key}")->plainTextToken;
        }

        $this->ctx = [
            'tenant' => $primary,
            'otherTenant' => $other,
            'users' => $users,
            'tokens' => $tokens,
            'fixtures' => [],
        ];
    }

    private function makeUser(?int $tenantId, UserType $type, string $role, string $email): User
    {
        $user = User::query()->create([
            'tenant_id' => $tenantId,
            'user_type' => $type,
            'name' => 'E2E '.$role,
            'email' => $email,
            'password' => Hash::make('password123'),
        ]);
        $user->assignRole($role);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $case
     */
    private function runCase(string $base, string $method, string $specPath, array $case): void
    {
        $name = $case['name'] ?? 'case';

        $tenantKey = ($case['tenant'] ?? 'primary') === 'other' ? 'otherTenant' : 'tenant';
        $tenant = $this->ctx[$tenantKey];

        $path = isset($case['path']) && is_callable($case['path'])
            ? $case['path']($this->ctx)
            : $specPath;

        $headers = ['Accept' => 'application/json'];
        if (! is_null($tenant)) {
            $headers['X-Tenant-ID'] = (string) $tenant->id;
        }
        if (! empty($case['as'])) {
            $headers['Authorization'] = 'Bearer '.$this->ctx['tokens'][$case['as']];
        }
        $headers = array_merge($headers, $this->resolveDynamicValues($case['headers'] ?? []));

        $timeout = max(1, (int) $this->option('timeout'));
        $request = Http::withHeaders($headers)
            ->timeout($timeout)
            ->connectTimeout(min($timeout, 5))
            ->acceptJson();
        $body = $this->resolveDynamicValues($case['body'] ?? []);

        try {
            $response = $request->{$method}($base.$path, $body);
        } catch (Throwable $e) {
            $this->reportCase($name, [['request', false, 'reachable app', $this->sanitize($e->getMessage())]]);

            return;
        }

        $this->ctx['response'] = $response;
        $json = $response->json() ?? [];

        $checks = [];

        $expect = $this->resolveDynamicValues($case['expect'] ?? []);
        $expectedStatus = $expect['status'] ?? null;

        if ($expectedStatus !== null) {
            $checks[] = ['status', $response->status() === $expectedStatus, $expectedStatus, $response->status()];
        }

        // 5xx só é aceitável se o caso o esperava explicitamente; caso contrário
        // é falha do app — registra o corpo (sanitizado) e aciona o circuit breaker.
        if ($response->serverError() && ! (is_int($expectedStatus) && $expectedStatus >= 500)) {
            $checks[] = ['no unexpected 5xx', false, '<5xx', $response->status()];
            $checks[] = ['response body', false, 'non-5xx JSON', $this->sanitize(mb_substr($response->body(), 0, 1000))];

            if (! $this->option('continue-on-error')) {
                $this->aborted = true;
            }
        }
        foreach (($expect['json'] ?? []) as $jsonPath => $expected) {
            $actual = data_get($json, $jsonPath);
            $checks[] = ["json: {$jsonPath}", $this->looseEquals($expected, $actual), $expected, $actual];
        }

        if (isset($case['db']) && is_callable($case['db'])) {
            foreach ((array) $case['db']($this->ctx) as $label => $pair) {
                [$expected, $actual] = $pair;
                $checks[] = ["db: {$label}", $this->looseEquals($expected, $actual), $expected, $actual];
            }
        }

        $this->reportCase($name, $checks);
    }

    private function looseEquals(mixed $expected, mixed $actual): bool
    {
        if (is_null($expected) || is_null($actual) || is_bool($expected)) {
            return $expected === $actual;
        }
        if (is_scalar($expected) && is_scalar($actual)) {
            return (string) $expected === (string) $actual;
        }

        return $expected == $actual;
    }

    private function resolveDynamicValues(mixed $value): mixed
    {
        if (is_callable($value)) {
            return $value($this->ctx);
        }

        if (! is_array($value)) {
            return $value;
        }

        $resolved = [];
        foreach ($value as $key => $item) {
            $resolved[$key] = $this->resolveDynamicValues($item);
        }

        return $resolved;
    }

    /**
     * @param  array<int, array{0: string, 1: bool, 2: mixed, 3: mixed}>  $checks
     */
    private function reportCase(string $name, array $checks): void
    {
        $ok = array_reduce($checks, fn (bool $carry, array $c): bool => $carry && $c[1], true) && $checks !== [];

        $ok ? $this->passed++ : $this->failed++;
        $this->line(($ok ? '<fg=green>✓</>' : '<fg=red>✗</>')." {$name}");

        foreach ($checks as [$label, $pass, $expected, $actual]) {
            if (! $pass) {
                $this->line(sprintf(
                    '    <fg=red>✗ %s</>  esperado=%s  obtido=%s',
                    $label,
                    $this->stringify($expected),
                    $this->stringify($actual),
                ));
            }
        }
    }

    private function stringify(mixed $value): string
    {
        if (is_null($value)) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value) ?: gettype($value);
    }

    /**
     * Redige tokens/segredos de qualquer texto que vá para o output (corpo 5xx,
     * mensagens de exceção). O runner nunca deve imprimir Bearer ou senha.
     */
    private function sanitize(string $text): string
    {
        $text = preg_replace('/Bearer\s+[A-Za-z0-9|._~+\/=-]+/', 'Bearer [REDACTED]', $text) ?? $text;

        return preg_replace(
            '/("(?:token|plainTextToken|access_token|password|current_password)"\s*:\s*")[^"]*(")/i',
            '$1[REDACTED]$2',
            $text,
        ) ?? $text;
    }

    /**
     * DB é "descartável" se o nome sinaliza teste/e2e — nunca o DB de dev/prod.
     * Barra mutação acidental no banco errado (o --force-db é o escape explícito).
     */
    private function isDisposableDatabase(string $database): bool
    {
        return preg_match('/(e2e|test)/i', $database) === 1;
    }

    /**
     * Canário: prova que a app SERVIDA (via HTTP) enxerga as fixtures que o runner
     * acabou de criar no seu DB. Um token de fixture só autentica em /auth/me se
     * servidor e runner compartilham o mesmo banco — se não, aborta antes de mutar.
     */
    private function canaryServerDbAligned(string $base): bool
    {
        $token = $this->ctx['tokens']['admin'] ?? null;
        $tenant = $this->ctx['tenant'] ?? null;

        if ($token === null || $tenant === null) {
            return false;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$token,
                'X-Tenant-ID' => (string) $tenant->id,
            ])
                ->timeout(max(1, (int) $this->option('timeout')))
                ->acceptJson()
                ->get($base.'/api/v1/core/auth/me');
        } catch (Throwable) {
            return false;
        }

        return $response->status() === 200;
    }

    private function teardownFixtures(): void
    {
        try {
            foreach (($this->ctx['users'] ?? []) as $user) {
                $user->tokens()->delete();
                $user->forceDelete();
            }
            foreach (['tenant', 'otherTenant'] as $key) {
                ($this->ctx[$key] ?? null)?->forceDelete();
            }
        } catch (Throwable $e) {
            // Teardown incompleto deixa fixtures órfãs; sinalizar via exit code.
            $this->warn('teardown parcial: '.$this->sanitize($e->getMessage()));
            $this->failed++;
        }
    }
}
