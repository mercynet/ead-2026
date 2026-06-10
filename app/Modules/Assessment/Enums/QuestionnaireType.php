<?php

namespace App\Modules\Assessment\Enums;

enum QuestionnaireType: string
{
    case LESSON = 'lesson';
    case COURSE = 'course';
    case STANDALONE = 'standalone';
}
