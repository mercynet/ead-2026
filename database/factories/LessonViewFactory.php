<?php

namespace Database\Factories;

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Learning\Models\Lesson;
use App\Modules\Learning\Models\LessonView;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LessonView>
 */
class LessonViewFactory extends Factory
{
    protected $model = LessonView::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'lesson_id' => Lesson::factory(),
            'viewed_at' => now(),
        ];
    }
}
