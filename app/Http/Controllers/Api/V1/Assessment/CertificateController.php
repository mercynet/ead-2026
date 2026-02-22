<?php

namespace App\Http\Controllers\Api\V1\Assessment;

use App\Actions\Assessment\Certificate\ListCertificatesAction;
use App\Actions\Assessment\Certificate\ShowCertificateAction;
use App\Actions\Assessment\Certificate\VerifyCertificateAction;
use App\Http\Context\ApiContext;
use App\Http\Controllers\Controller;
use App\Http\Resources\Assessment\CertificateResource;
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
     */
    public function verify(string $certificateNumber): JsonResponse
    {
        $result = $this->verifyCertificateAction->handle($certificateNumber);

        return response()->json($result);
    }
}
