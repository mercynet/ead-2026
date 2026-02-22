<?php

namespace App\Actions\Learning\Course;

use App\Models\Course;
use Illuminate\Support\Str;

class UpdateCourseAction
{
    public function handle(Course $course, array $attributes): Course
    {
        if (isset($attributes['title'])) {
            $course->title = $attributes['title'];
            $course->slug = Str::slug($attributes['title']);
        }

        $fillableFields = [
            'description',
            'short_description',
            'target_audience',
            'requirements',
            'what_you_will_learn',
            'what_you_will_build',
            'status',
            'thumbnail',
            'banner',
            'level',
            'price_cents',
            'duration_hours',
            'access_days',
            'is_featured',
            'certificate_enabled',
            'certificate_min_progress',
            'certificate_requires_quiz',
            'certificate_min_score',
            'is_active',
        ];

        foreach ($fillableFields as $field) {
            if (array_key_exists($field, $attributes)) {
                $course->{$field} = $attributes[$field];
            }
        }

        if (isset($attributes['status']) && $attributes['status'] === 'published' && ! $course->published_at) {
            $course->published_at = now();
        }

        $course->save();

        return $course->fresh();
    }
}
