<?php

namespace App\Modules\Assessment\Enums;

enum QuizAttemptStatus: string
{
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
}
