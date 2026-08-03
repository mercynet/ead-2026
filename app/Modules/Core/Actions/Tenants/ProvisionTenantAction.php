<?php

namespace App\Modules\Core\Actions\Tenants;

use App\Modules\Core\Data\Tenants\ProvisionTenantResult;
use App\Modules\Core\Enums\UserType;
use App\Modules\Core\Exceptions\AdminPromotionRequiredException;
use App\Modules\Core\Exceptions\TenantAlreadyExistsException;
use App\Modules\Core\Exceptions\TenantDomainCreationCollisionException;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Shared\Contracts\TenantProvisioningParticipant;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\UniqueConstraintViolationException;

class ProvisionTenantAction
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly TenantProvisioningParticipant $tenantProvisioningParticipant,
    ) {}

    /**
     * @param  array{name: string, domain: string, database?: string|null, description?: string|null}  $tenantData
     * @param  array{name: string, email: string, cpf?: string|null}  $adminData
     */
    public function handle(
        array $tenantData,
        array $adminData,
        string $password,
        bool $reuseExistingTenant = false,
        bool $promoteExistingAdmin = false,
    ): ProvisionTenantResult {
        try {
            return $this->transactionalProvision($tenantData, $adminData, $password, $reuseExistingTenant, $promoteExistingAdmin);
        } catch (TenantDomainCreationCollisionException $exception) {
            $existingTenant = Tenant::query()->where('domain', $tenantData['domain'])->first();

            if ($existingTenant === null) {
                throw $exception->constraintViolation;
            }

            if (! $reuseExistingTenant) {
                throw new TenantAlreadyExistsException;
            }

            return $this->transactionalProvision($tenantData, $adminData, $password, true, $promoteExistingAdmin);
        }
    }

    /**
     * @param  array{name: string, domain: string, database?: string|null, description?: string|null}  $tenantData
     * @param  array{name: string, email: string, cpf?: string|null}  $adminData
     */
    private function transactionalProvision(
        array $tenantData,
        array $adminData,
        string $password,
        bool $reuseExistingTenant,
        bool $promoteExistingAdmin,
    ): ProvisionTenantResult {
        return $this->database->transaction(function () use ($tenantData, $adminData, $password, $reuseExistingTenant, $promoteExistingAdmin): ProvisionTenantResult {
            $tenant = Tenant::query()->where('domain', $tenantData['domain'])->first();
            $tenantCreated = false;

            if ($tenant === null) {
                $tenant = $this->createTenant($tenantData);
                $tenantCreated = true;
            } elseif (! $reuseExistingTenant) {
                throw new TenantAlreadyExistsException;
            }

            $admin = User::query()
                ->where('tenant_id', $tenant->id)
                ->where('email', $adminData['email'])
                ->first();
            $adminCreated = false;
            $adminPromoted = false;

            if ($admin === null) {
                $admin = User::query()->create([
                    'tenant_id' => $tenant->id,
                    'user_type' => UserType::Admin,
                    'name' => $adminData['name'],
                    'email' => $adminData['email'],
                    'password' => $password,
                    'cpf' => $adminData['cpf'] ?? null,
                ]);
                $adminCreated = true;
            } elseif ($admin->user_type !== UserType::Admin) {
                if (! $promoteExistingAdmin) {
                    throw new AdminPromotionRequiredException($admin);
                }

                $admin->fill(['user_type' => UserType::Admin]);
                $admin->save();
                $adminPromoted = true;
            }

            $admin->assignRole(UserType::Admin->value);
            $this->tenantProvisioningParticipant->provision($tenant->id, $admin->id);

            return new ProvisionTenantResult($tenant, $admin, $tenantCreated, $adminCreated, $adminPromoted);
        });
    }

    /**
     * @param  array{name: string, domain: string, database?: string|null, description?: string|null}  $tenantData
     */
    private function createTenant(array $tenantData): Tenant
    {
        try {
            return Tenant::query()->create([
                'name' => $tenantData['name'],
                'domain' => $tenantData['domain'],
                'database' => $tenantData['database'] ?? null,
                'description' => $tenantData['description'] ?? null,
                'is_active' => true,
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            if (! $this->isTenantDomainInsert($exception)) {
                throw $exception;
            }

            throw new TenantDomainCreationCollisionException($exception);
        }
    }

    private function isTenantDomainInsert(UniqueConstraintViolationException $exception): bool
    {
        return preg_match('/insert\s+into\s+[`"]?tenants[`"]?\s*\((?=[^)]*[`"]?domain[`"]?)/i', $exception->getSql()) === 1;
    }
}
