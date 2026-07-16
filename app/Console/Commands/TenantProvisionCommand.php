<?php

namespace App\Console\Commands;

use App\Modules\Core\Enums\UserType;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Runbook de bootstrap: provisiona (idempotente) um tenant e seu primeiro admin,
 * semeando o RBAC canônico. É o ponto de entrada para operar o onboarding
 * invite-only — o admin criado aqui é quem passa a emitir convites.
 *
 * Idempotente: reexecutar com o mesmo domínio/email não duplica nem sobrescreve
 * a senha de um admin já existente.
 */
class TenantProvisionCommand extends Command
{
    protected $signature = 'tenant:provision
        {--name= : Nome do tenant}
        {--domain= : Domínio que resolve o tenant (único)}
        {--admin-name= : Nome do primeiro admin}
        {--admin-email= : Email do primeiro admin}
        {--admin-password= : Senha do admin (se omitida, gera uma forte e exibe uma vez)}
        {--promote : Promove um usuário existente não-admin a admin (senão, recusa a promoção silenciosa)}
        {--description= : Descrição do tenant (opcional)}';

    protected $description = 'Provisiona (idempotente) um tenant e seu primeiro admin, semeando RBAC';

    public function handle(): int
    {
        /** @var array<string, string> $data */
        $data = [
            'name' => trim((string) $this->option('name')),
            'domain' => trim((string) $this->option('domain')),
            'admin_name' => trim((string) $this->option('admin-name')),
            'admin_email' => trim((string) $this->option('admin-email')),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'domain' => ['required', 'string', 'max:255'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        // A senha fornecida via --admin-password precisa da MESMA política dos
        // FormRequests (min:8); a gerada automaticamente já é forte por construção.
        $providedPassword = trim((string) $this->option('admin-password'));
        if ($providedPassword !== '') {
            $passwordValidator = Validator::make(
                ['admin_password' => $providedPassword],
                ['admin_password' => ['string', 'min:8']],
            );

            if ($passwordValidator->fails()) {
                foreach ($passwordValidator->errors()->all() as $error) {
                    $this->components->error($error);
                }

                return self::FAILURE;
            }
        }

        $this->seedRbac();

        $tenant = Tenant::query()->firstOrCreate(
            ['domain' => $data['domain']],
            [
                'name' => $data['name'],
                'database' => null,
                'description' => ($description = trim((string) $this->option('description'))) !== '' ? $description : null,
                'is_active' => true,
            ],
        );

        $this->components->info($tenant->wasRecentlyCreated
            ? "Tenant criado: {$tenant->name} (#{$tenant->id}, {$tenant->domain})"
            : "Tenant já existia: {$tenant->name} (#{$tenant->id}, {$tenant->domain})");

        $ensured = $this->ensureAdmin($tenant, $data);

        if ($ensured === null) {
            return self::FAILURE;
        }

        [$admin, $generatedPassword] = $ensured;

        $admin->syncRoles([UserType::Admin->value]);

        if ($generatedPassword !== null) {
            $this->newLine();
            $this->components->warn('Senha gerada (exibida só agora — guarde-a):');
            $this->line("  {$generatedPassword}");
        }

        $this->newLine();
        $this->components->info('Provisionamento concluído. O admin já pode emitir convites via POST /api/v1/core/invitations.');

        return self::SUCCESS;
    }

    private function seedRbac(): void
    {
        $this->callSilent('db:seed', ['--class' => PermissionsSeeder::class, '--force' => true]);
        $this->callSilent('db:seed', ['--class' => RolesSeeder::class, '--force' => true]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @param  array<string, string>  $data
     * @return array{0: User, 1: string|null}|null null = provisionamento recusado
     */
    private function ensureAdmin(Tenant $tenant, array $data): ?array
    {
        $admin = User::query()
            ->where('tenant_id', $tenant->id)
            ->where('email', $data['admin_email'])
            ->first();

        if ($admin instanceof User) {
            // Promover um student/instructor existente a admin é escalada de
            // privilégio silenciosa (ex.: typo no email). Só com --promote explícito.
            if ($admin->user_type !== UserType::Admin) {
                if (! $this->option('promote')) {
                    $this->components->error("Usuário {$admin->email} (#{$admin->id}) já existe como '{$admin->user_type->value}' — recuso promoção silenciosa a admin. Reexecute com --promote se a intenção for promover.");

                    return null;
                }

                $admin->update(['user_type' => UserType::Admin]);
                $this->components->warn("Usuário {$admin->email} (#{$admin->id}) promovido a admin (--promote).");
            }

            $this->components->info("Admin já existia: {$admin->email} (#{$admin->id}) — senha preservada");

            return [$admin, null];
        }

        $providedPassword = trim((string) $this->option('admin-password'));
        $generatedPassword = $providedPassword === '' ? Str::password(16) : null;

        $admin = User::query()->create([
            'tenant_id' => $tenant->id,
            'user_type' => UserType::Admin,
            'name' => $data['admin_name'],
            'email' => $data['admin_email'],
            'password' => $providedPassword !== '' ? $providedPassword : $generatedPassword,
        ]);

        $this->components->info("Admin criado: {$admin->email} (#{$admin->id})");

        return [$admin, $generatedPassword];
    }
}
