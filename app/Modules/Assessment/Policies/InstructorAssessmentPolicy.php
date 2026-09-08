<?php

namespace App\Modules\Assessment\Policies;

use App\Modules\Assessment\Models\Questionnaire;
use App\Modules\Assessment\Models\QuizQuestion;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;

class InstructorAssessmentPolicy
{
    public function questionnaire(User $user, ?Tenant $tenant, ?Questionnaire $questionnaire = null, string $permission = 'assessment.questionnaires.view'): bool
    {
        return $this->owns($user, $tenant, $questionnaire?->getAttribute('tenant_id'), $questionnaire?->getAttribute('instructor_id'))
            && $questionnaire !== null
            && $user->getAllPermissions()->contains('name', $permission);
    }

    public function question(User $user, ?Tenant $tenant, ?QuizQuestion $question = null, string $permission = 'assessment.questions.view'): bool
    {
        return $this->owns($user, $tenant, $question?->getAttribute('tenant_id'), $question?->getAttribute('instructor_id'))
            && $question !== null
            && $user->getAllPermissions()->contains('name', $permission);
    }

    private function owns(User $user, ?Tenant $tenant, ?int $tenantId, ?int $instructorId): bool
    {
        return $user->isInstructor()
            && $tenant !== null
            && $user->belongsToTenant($tenant)
            && (int) $tenantId === (int) $tenant->id
            && (int) $instructorId === (int) $user->id;
    }
}
