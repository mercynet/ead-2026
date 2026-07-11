<?php

namespace App\Modules\Learning\Enums;

enum LessonMediaProgressStrategy: string
{
    case EightyPercent = '80_percent';
    case FullDuration = 'full_duration';
    case Manual = 'manual';
    case TimeBased = 'time_based';
}
