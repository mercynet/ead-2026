<?php

namespace App\Modules\Assessment\Actions\Certificate;

use App\Modules\Assessment\Models\Certificate;
use App\Shared\Http\ApiContext;

/**
 * Get a certificate by ID.
 *
 * @group Pedagógico / Certificados
 *
 * @apiResource App\Modules\Assessment\Http\Resources\CertificateResource
 *
 * @apiResourceModel App\Modules\Assessment\Models\Certificate
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
