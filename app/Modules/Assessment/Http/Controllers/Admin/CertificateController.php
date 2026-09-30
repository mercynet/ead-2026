<?php

namespace App\Modules\Assessment\Http\Controllers\Admin;

use App\Modules\Assessment\Actions\Certificate\RevokeCertificateAction;
use App\Modules\Assessment\Http\Resources\CertificateResource;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Controller;
use Illuminate\Support\Facades\Gate;

/**
 * @group Admin · Assessment
 *
 * Operações administrativas tenant-wide sobre certificados.
 */
class CertificateController extends Controller
{
    public function __construct(
        private readonly RevokeCertificateAction $revokeCertificateAction,
    ) {}

    /**
     * Revogar Certificado
     *
     * Revoga um certificado emitido no tenant atual. A operação é idempotente.
     */
    public function revoke(int $id, ApiContext $context): CertificateResource
    {
        Gate::forUser($context->requiredUser())->authorize('assessment.certificates.revoke', [$context->requiredTenant()]);

        return CertificateResource::make($this->revokeCertificateAction->handle($id, $context));
    }
}
