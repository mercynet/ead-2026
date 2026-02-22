<?php

namespace App\Actions\Assessment\Certificate;

use App\Http\Context\ApiContext;
use App\Models\Certificate;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;

/**
 * List all certificates for the current user.
 *
 * @group Pedagógico / Certificados
 *
 * @apiResource App\Http\Resources\Assessment\CertificateResource
 *
 * @apiResourceCollection Illuminate\Pagination\CursorPaginator<App\Http\Resources\Assessment\CertificateResource>
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
