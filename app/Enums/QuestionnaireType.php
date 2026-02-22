<?php

namespace App\Enums;

enum QuestionnaireType: string
{
    case LESSON = 'lesson';
    case COURSE = 'course';
    case STANDALONE = 'standalone';
}
