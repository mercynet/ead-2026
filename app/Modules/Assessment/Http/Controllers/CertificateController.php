<?php

namespace App\Modules\Assessment\Http\Controllers;

use App\Modules\Assessment\Actions\Certificate\ListCertificatesAction;
use App\Modules\Assessment\Actions\Certificate\ShowCertificateAction;
use App\Modules\Assessment\Actions\Certificate\VerifyCertificateAction;
use App\Modules\Assessment\Http\Resources\CertificateResource;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * @group Pedagógico / Certificados
 *
 * Gerenciamento de certificados
 */
class CertificateController extends Controller
{
    public function __construct(
        private readonly ListCertificatesAction $listCertificatesAction,
        private readonly ShowCertificateAction $showCertificateAction,
        private readonly VerifyCertificateAction $verifyCertificateAction,
    ) {}

    /**
     * Listar Meus Certificados
     */
    public function index(ApiContext $context): AnonymousResourceCollection
    {
        Gate::forUser($context->user)->authorize('assessment.certificates.list', [$context->tenant]);

        $paginator = $this->listCertificatesAction->handle(request(), $context);

        return CertificateResource::collection($paginator);
    }

    /**
     * Ver Certificado
     */
    public function show(int $id, ApiContext $context): CertificateResource
    {
        Gate::forUser($context->user)->authorize('assessment.certificates.view', [$context->tenant]);

        $certificate = $this->showCertificateAction->handle($id, $context);

        return CertificateResource::make($certificate);
    }

    /**
     * Verificar Certificado
     *
     * Endpoint público para verificação de certificados.
     *
     * @unauthenticated
     */
    public function verify(string $certificateNumber): JsonResponse
    {
        $result = $this->verifyCertificateAction->handle($certificateNumber);

        return response()->json($result);
    }
}
