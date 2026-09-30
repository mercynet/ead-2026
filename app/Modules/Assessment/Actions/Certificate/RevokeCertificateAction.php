<?php

namespace App\Modules\Assessment\Actions\Certificate;

use App\Modules\Assessment\Enums\CertificateStatus;
use App\Modules\Assessment\Models\Certificate;
use App\Shared\Http\ApiContext;

class RevokeCertificateAction
{
    public function handle(int $id, ApiContext $context): Certificate
    {
        $query = Certificate::query()->with(['course:id,title,slug']);

        if ($context->tenant !== null) {
            $query->where('tenant_id', $context->tenant->id);
        }

        $certificate = $query->findOrFail($id);

        if ($certificate->getAttribute('status') !== CertificateStatus::REVOKED->value) {
            $certificate->fill(['status' => CertificateStatus::REVOKED->value]);
            $certificate->save();
        }

        return $certificate->refresh()->load('course:id,title,slug');
    }
}
