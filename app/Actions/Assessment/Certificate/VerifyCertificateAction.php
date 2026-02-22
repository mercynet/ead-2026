<?php

namespace App\Actions\Assessment\Certificate;

use App\Models\Certificate;

/**
 * Verify a certificate by certificate number (public endpoint).
 *
 * @group Pedagógico / Certificados
 *
 * @response 200 {
 *   "valid": true,
 *   "certificate": {
 *     "certificate_number": "CERT-2026-A1B2C3D4",
 *     "course_title": "Curso de Laravel",
 *     "user_name": "João Silva",
 *     "issued_at": "2026-01-15T10:30:00Z",
 *     "status": "issued"
 *   }
 * }
 */
class VerifyCertificateAction
{
    public function handle(string $certificateNumber): array
    {
        $certificate = Certificate::query()
            ->with(['course:id,title', 'user:id,name'])
            ->where('certificate_number', $certificateNumber)
            ->first();

        if (! $certificate) {
            return [
                'valid' => false,
                'message' => 'Certificate not found.',
            ];
        }

        if ($certificate->status === 'revoked') {
            return [
                'valid' => false,
                'certificate' => [
                    'certificate_number' => $certificate->certificate_number,
                    'course_title' => $certificate->course?->title,
                    'user_name' => $certificate->user?->name,
                    'issued_at' => $certificate->issued_at?->toIso8601String(),
                    'status' => 'revoked',
                ],
                'message' => 'This certificate has been revoked.',
            ];
        }

        return [
            'valid' => true,
            'certificate' => [
                'certificate_number' => $certificate->certificate_number,
                'course_title' => $certificate->course?->title,
                'user_name' => $certificate->user?->name,
                'issued_at' => $certificate->issued_at?->toIso8601String(),
                'status' => $certificate->status,
            ],
        ];
    }
}
