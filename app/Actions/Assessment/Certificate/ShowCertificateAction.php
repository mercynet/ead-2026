<?php

namespace App\Actions\Assessment\Certificate;

use App\Http\Context\ApiContext;
use App\Models\Certificate;

/**
 * Get a certificate by ID.
 *
 * @group Pedagógico / Certificados
 *
 * @apiResource App\Http\Resources\Assessment\CertificateResource
 *
 * @apiResourceModel App\Models\Certificate
 */
class ShowCertificateAction
{
    public function handle(int $id, ApiContext $context): Certificate
    {
        return Certificate::query()
            ->with(['course:id,title,slug', 'enrollment:id'])
            ->where('user_id', $context->user->id)
            ->findOrFail($id);
    }
}
