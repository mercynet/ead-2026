<?php

use App\Modules\Assessment\Models\Questionnaire;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Assessment\Models\QuizAttemptAnswer;
use App\Modules\Core\Enums\UserType;
use App\Modules\Core\Models\User;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\Enrollment;
use Illuminate\Support\Facades\DB;

it('keeps Instructor results query count bounded as owned result rows grow', function (): void {
    $tenant = makeTenant();
    [$instructor, $headers] = actingAsUserType(UserType::Instructor, $tenant);
    $student = User::factory()->student()->forTenant($tenant)->create();
    $secondStudent = User::factory()->student()->forTenant($tenant)->create();
    $fixtureNumber = 0;

    $createResultFixture = function () use ($tenant, $instructor, $student, $secondStudent, &$fixtureNumber): void {
        $course = Course::factory()->for($tenant)->create(['instructor_id' => $instructor->id]);
        $questionnaire = Questionnaire::factory()->course()->for($tenant)->create([
            'instructor_id' => $instructor->id,
            'quizable_type' => 'course',
            'quizable_id' => $course->id,
        ]);
        $fixtureStudent = $fixtureNumber++ % 2 === 0 ? $student : $secondStudent;

        Enrollment::factory()->active()->for($tenant)->for($fixtureStudent, 'user')->for($course, 'course')->create();
        $attempt = QuizAttempt::factory()->completed()->for($tenant)->for($fixtureStudent, 'user')->for($questionnaire, 'questionnaire')->create();

        QuizAttemptAnswer::factory()->for($tenant)->for($attempt, 'attempt')->createMany([
            ['question_snapshot' => ['id' => 1, 'question' => 'Question 1', 'type' => 'single_choice', 'options' => [], 'correct_options' => [0], 'points' => 1]],
            ['question_snapshot' => ['id' => 2, 'question' => 'Question 2', 'type' => 'single_choice', 'options' => [], 'correct_options' => [0], 'points' => 1]],
        ]);
    };

    $createResultFixture();

    $this->getJson('/api/v1/instructor/assessment/results', $headers)->assertSuccessful();

    DB::enableQueryLog();
    DB::flushQueryLog();
    $smallResponse = $this->getJson('/api/v1/instructor/assessment/results', $headers);
    $smallQueryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    $smallResponse->assertSuccessful();

    $createResultFixture();
    $createResultFixture();
    $createResultFixture();

    DB::enableQueryLog();
    DB::flushQueryLog();
    $largeResponse = $this->getJson('/api/v1/instructor/assessment/results', $headers);
    $largeQueryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    $largeResponse->assertSuccessful();

    expect($largeResponse->json('data'))->toHaveCount(4)
        ->and($largeQueryCount)->toBeLessThanOrEqual($smallQueryCount + 2);
});
