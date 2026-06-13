<?php

namespace App\Console\Commands;

use App\Modules\Core\Enums\UserType;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Console\Command;
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
 */
class E2eRunCommand extends Command
{
    protected $signature = 'e2e:run {spec : Caminho do spec relativo a tests/e2e-http (ex.: learning/courses-store)}
        {--base= : Base URL do app rodando (default: config app.url)}
        {--keep : Não limpar as fixtures efêmeras no fim (debug)}';

    protected $description = 'Executa um spec E2E contra o app rodando (HTTP real + asserts de DB)';

    /** @var array<string, mixed> */
    private array $ctx = [];

    private int $passed = 0;

    private int $failed = 0;

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('e2e:run não roda em produção.');

            return self::FAILURE;
        }

        $spec = $this->loadSpec((string) $this->argument('spec'));
        if ($spec === null) {
            return self::FAILURE;
        }

        $base = rtrim((string) ($this->option('base') ?: config('app.url')), '/');
        [$method, $specPath] = $this->parseEndpoint($spec['endpoint']);

        $this->components->info("E2E: {$spec['endpoint']}  →  {$base}");

        try {
            $this->bootFixtures();

            if (isset($spec['setup']) && is_callable($spec['setup'])) {
                $this->ctx['fixtures'] = (array) $spec['setup']($this->ctx);
            }

            foreach ($spec['cases'] as $case) {
                $this->runCase($base, $method, $specPath, $case);
            }
        } catch (Throwable $e) {
            $this->error('Erro no runner: '.$e->getMessage());
            $this->failed++;
        } finally {
            if (isset($spec['cleanup']) && is_callable($spec['cleanup'])) {
                try {
                    $spec['cleanup']($this->ctx);
                } catch (Throwable $e) {
                    $this->warn('cleanup do spec falhou: '.$e->getMessage());
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

        $suffix = uniqid();

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
        $headers = array_merge($headers, $case['headers'] ?? []);

        $request = Http::withHeaders($headers)->acceptJson();
        $body = $case['body'] ?? [];

        try {
            $response = $request->{$method}($base.$path, $body);
        } catch (Throwable $e) {
            $this->reportCase($name, [['request', false, 'reachable app', $e->getMessage()]]);

            return;
        }

        $this->ctx['response'] = $response;
        $json = $response->json() ?? [];

        $checks = [];

        $expect = $case['expect'] ?? [];
        if (isset($expect['status'])) {
            $checks[] = ['status', $response->status() === $expect['status'], $expect['status'], $response->status()];
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

    private function teardownFixtures(): void
    {
        try {
            foreach (($this->ctx['users'] ?? []) as $user) {
                $user->tokens()->delete();
                $user->forceDelete();
            }
            foreach (['tenant', 'otherTenant'] as $key) {
                $this->ctx[$key]?->forceDelete();
            }
        } catch (Throwable $e) {
            $this->warn('teardown parcial: '.$e->getMessage());
        }
    }
}
