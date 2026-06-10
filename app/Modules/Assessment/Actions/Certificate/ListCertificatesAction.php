<?php

namespace App\Modules\Assessment\Actions\Certificate;

use App\Modules\Assessment\Models\Certificate;
use App\Shared\Http\ApiContext;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;

/**
 * List all certificates for the current user.
 *
 * @group Pedagógico / Certificados
 *
 * @apiResource App\Modules\Assessment\Http\Resources\CertificateResource
 *
 * @apiResourceCollection Illuminate\Pagination\CursorPaginator<App\Modules\Assessment\Http\Resources\CertificateResource>
 */
class ListCertificatesAction
{
    public function handle(Request $request, ApiContext $context): CursorPaginator
    {
        $query = Certificate::query()
            ->with(['course:id,title,slug'])
            ->where('user_id', $context->user->id)
            ->orderByDesc('issued_at');

        return $query->cursorPaginate(15);
    }
}
