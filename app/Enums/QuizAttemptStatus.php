<?php

namespace App\Enums;

enum QuizAttemptStatus: string
{
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
}
