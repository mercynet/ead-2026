<?php

use App\Modules\Assessment\Models\Questionnaire;
use App\Modules\Assessment\Models\QuestionnaireQuestion;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Assessment\Models\QuizAttemptAnswer;
use App\Modules\Assessment\Models\QuizQuestion;
use App\Modules\Core\Enums\UserType;
use App\Modules\Core\Models\User;
use App\Modules\Learning\Models\Category;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\CourseModule;
use App\Modules\Learning\Models\Enrollment;
use App\Modules\Learning\Models\Lesson;

function instructorQuestionPayload(string $question = 'Qual é a resposta?'): array
{
    return [
        'question' => $question,
        'type' => 'single_choice',
        'options' => [
            ['text' => 'A'],
            ['text' => 'B'],
        ],
        'correct_options' => [0],
        'explanation' => 'Feedback pedagógico.',
        'points' => 2,
    ];
}

beforeEach(function (): void {
    $this->tenant = makeTenant();
    [$this->instructor, $this->headers] = actingAsUserType(UserType::Instructor, $this->tenant);
    $this->otherInstructor = User::factory()->instructor()->forTenant($this->tenant)->create();
    $this->ownCourse = Course::factory()->for($this->tenant)->create(['instructor_id' => $this->instructor->id]);
    $this->otherCourse = Course::factory()->for($this->tenant)->create(['instructor_id' => $this->otherInstructor->id]);
    $this->ownModule = CourseModule::factory()->for($this->tenant)->for($this->ownCourse)->create();
    $this->ownLesson = Lesson::factory()->for($this->tenant)->for($this->ownModule)->create();
    $this->otherModule = CourseModule::factory()->for($this->tenant)->for($this->otherCourse)->create();
    $this->otherLesson = Lesson::factory()->for($this->tenant)->for($this->otherModule)->create();
});

it('exposes the canonical Instructor Assessment surface', function (): void {
    $this->getJson('/api/v1/instructor/assessment/questionnaires', $this->headers)
        ->assertSuccessful();
});

it('isolates Questionnaire and Question CRUD by pedagogical owner', function (): void {
    $ownQuestionnaire = Questionnaire::factory()->course()->for($this->tenant)->create([
        'instructor_id' => $this->instructor->id,
        'quizable_type' => 'course',
        'quizable_id' => $this->ownCourse->id,
    ]);
    $otherQuestionnaire = Questionnaire::factory()->course()->for($this->tenant)->create([
        'instructor_id' => $this->otherInstructor->id,
        'quizable_type' => 'course',
        'quizable_id' => $this->otherCourse->id,
    ]);
    $otherQuestion = QuizQuestion::factory()->for($this->tenant)->create([
        'instructor_id' => $this->otherInstructor->id,
    ]);

    $this->getJson('/api/v1/instructor/assessment/questionnaires', $this->headers)
        ->assertJsonFragment(['id' => $ownQuestionnaire->id])
        ->assertJsonMissing(['id' => $otherQuestionnaire->id]);

    assertApiErrorEnvelope(
        $this->patchJson('/api/v1/instructor/assessment/questionnaires/'.$otherQuestionnaire->id, ['title' => 'vazamento'], $this->headers),
        404,
        'not_found',
    );

    assertApiErrorEnvelope(
        $this->getJson('/api/v1/instructor/assessment/questions/'.$otherQuestion->id, $this->headers),
        404,
        'not_found',
    );
});

it('rejects foreign parents and keeps Admin-owned records invisible', function (): void {
    $adminQuestionnaire = Questionnaire::factory()->course()->for($this->tenant)->create([
        'instructor_id' => null,
        'quizable_type' => 'course',
        'quizable_id' => $this->ownCourse->id,
    ]);

    assertApiErrorEnvelope(
        $this->postJson('/api/v1/instructor/assessment/questionnaires', [
            'title' => 'Foreign parent',
            'type' => 'course',
            'quizable_type' => 'course',
            'quizable_id' => $this->otherCourse->id,
        ], $this->headers),
        422,
        'validation_error',
    );

    assertApiErrorEnvelope(
        $this->postJson('/api/v1/instructor/assessment/questionnaires', [
            'title' => 'Foreign lesson parent',
            'type' => 'lesson',
            'quizable_type' => 'lesson',
            'quizable_id' => $this->otherLesson->id,
        ], $this->headers),
        422,
        'validation_error',
    );

    $this->getJson('/api/v1/instructor/assessment/questionnaires', $this->headers)
        ->assertJsonMissing(['id' => $adminQuestionnaire->id]);
});

it('supports composition and blocks every mutation after the first attempt', function (): void {
    $questionnaire = Questionnaire::factory()->course()->for($this->tenant)->create([
        'instructor_id' => $this->instructor->id,
        'quizable_type' => 'course',
        'quizable_id' => $this->ownCourse->id,
    ]);
    $firstQuestion = QuizQuestion::factory()->for($this->tenant)->create(['instructor_id' => $this->instructor->id]);
    $secondQuestion = QuizQuestion::factory()->for($this->tenant)->create(['instructor_id' => $this->instructor->id]);

    $this->postJson('/api/v1/instructor/assessment/questionnaires/'.$questionnaire->id.'/questions', [
        'question_ids' => [$firstQuestion->id, $secondQuestion->id],
    ], $this->headers)->assertSuccessful();

    $this->patchJson('/api/v1/instructor/assessment/questionnaires/'.$questionnaire->id.'/questions/reorder', [
        'question_ids' => [$secondQuestion->id, $firstQuestion->id],
    ], $this->headers)->assertSuccessful();

    $student = User::factory()->student()->forTenant($this->tenant)->create();
    Enrollment::factory()->active()->for($this->tenant)->for($student, 'user')->for($this->ownCourse, 'course')->create();
    QuizAttempt::factory()->completed()->for($this->tenant)->for($student, 'user')->for($questionnaire, 'questionnaire')->create();

    assertApiErrorEnvelope(
        $this->patchJson('/api/v1/instructor/assessment/questionnaires/'.$questionnaire->id, ['title' => 'frozen'], $this->headers),
        422,
        'validation_error',
    );
    assertApiErrorEnvelope(
        $this->deleteJson('/api/v1/instructor/assessment/questionnaires/'.$questionnaire->id.'/questions/'.$firstQuestion->id, [], $this->headers),
        422,
        'validation_error',
    );
    assertApiErrorEnvelope(
        $this->patchJson('/api/v1/instructor/assessment/questions/'.$firstQuestion->id, ['question' => 'frozen'], $this->headers),
        422,
        'validation_error',
    );
});

it('exposes only enrolled own-course results and a least-privilege answer projection', function (): void {
    $questionnaire = Questionnaire::factory()->course()->for($this->tenant)->create([
        'instructor_id' => $this->instructor->id,
        'quizable_type' => 'course',
        'quizable_id' => $this->ownCourse->id,
    ]);
    $student = User::factory()->student()->forTenant($this->tenant)->create();
    Enrollment::factory()->active()->for($this->tenant)->for($student, 'user')->for($this->ownCourse, 'course')->create();
    $attempt = QuizAttempt::factory()->completed()->for($this->tenant)->for($student, 'user')->for($questionnaire, 'questionnaire')->create([
        'score' => 85,
        'passed' => true,
    ]);
    QuizAttemptAnswer::factory()->for($this->tenant)->for($attempt, 'attempt')->create([
        'question_snapshot' => ['id' => 1, 'question' => 'Q', 'explanation' => 'Feedback', 'correct_options' => [0]],
    ]);

    $this->getJson('/api/v1/instructor/assessment/results', $this->headers)
        ->assertSuccessful()
        ->assertJsonFragment(['attempt_id' => $attempt->id])
        ->assertJsonMissingPath('data.0.student.email')
        ->assertJsonMissingPath('data.0.answers.0.question_snapshot.correct_options');
});

it('completes own CRUD for Course/Lesson questionnaires and questions', function (): void {
    $courseResponse = $this->postJson('/api/v1/instructor/assessment/questionnaires', [
        'title' => 'Course own questionnaire',
        'type' => 'course',
        'quizable_type' => 'course',
        'quizable_id' => $this->ownCourse->id,
    ], $this->headers)->assertCreated();
    $courseQuestionnaireId = $courseResponse->json('data.id');

    $lessonResponse = $this->postJson('/api/v1/instructor/assessment/questionnaires', [
        'title' => 'Lesson own questionnaire',
        'type' => 'lesson',
        'quizable_type' => 'lesson',
        'quizable_id' => $this->ownLesson->id,
    ], $this->headers)->assertCreated();
    $lessonQuestionnaireId = $lessonResponse->json('data.id');

    $questionResponse = $this->postJson('/api/v1/instructor/assessment/questions', instructorQuestionPayload(), $this->headers)
        ->assertCreated();
    $questionId = $questionResponse->json('data.id');

    expect(QuizQuestion::query()->findOrFail($questionId)->instructor_id)->toBe($this->instructor->id);

    $this->patchJson('/api/v1/instructor/assessment/questionnaires/'.$courseQuestionnaireId, ['title' => 'Course changed'], $this->headers)
        ->assertOk();
    $this->patchJson('/api/v1/instructor/assessment/questions/'.$questionId, ['question' => 'Changed question'], $this->headers)
        ->assertOk();
    $this->deleteJson('/api/v1/instructor/assessment/questions/'.$questionId, [], $this->headers)
        ->assertOk();
    $this->deleteJson('/api/v1/instructor/assessment/questionnaires/'.$courseQuestionnaireId, [], $this->headers)
        ->assertOk();
    $this->deleteJson('/api/v1/instructor/assessment/questionnaires/'.$lessonQuestionnaireId, [], $this->headers)
        ->assertOk();
});

it('validates category ownership without changing the category taxonomy boundary', function (): void {
    $foreignCategory = Category::factory()->for(makeTenant())->create();
    $systemCategory = Category::factory()->system()->create();

    $response = $this->postJson('/api/v1/instructor/assessment/questions', [
        ...instructorQuestionPayload(),
        'category_ids' => [$foreignCategory->id],
    ], $this->headers);
    assertApiErrorEnvelope($response, 422, 'validation_error');

    $question = $this->postJson('/api/v1/instructor/assessment/questions', [
        ...instructorQuestionPayload('System category question'),
        'category_ids' => [$systemCategory->id],
    ], $this->headers)->assertCreated()->json('data');

    expect($question['categories'][0]['id'])->toBe($systemCategory->id);
});

it('does not expose another instructor result or an attempt from a different course', function (): void {
    $ownQuestionnaire = Questionnaire::factory()->course()->for($this->tenant)->create([
        'instructor_id' => $this->instructor->id,
        'quizable_type' => 'course',
        'quizable_id' => $this->ownCourse->id,
    ]);
    $otherQuestionnaire = Questionnaire::factory()->course()->for($this->tenant)->create([
        'instructor_id' => $this->otherInstructor->id,
        'quizable_type' => 'course',
        'quizable_id' => $this->otherCourse->id,
    ]);
    $student = User::factory()->student()->forTenant($this->tenant)->create();
    Enrollment::factory()->active()->for($this->tenant)->for($student, 'user')->for($this->ownCourse, 'course')->create();
    Enrollment::factory()->active()->for($this->tenant)->for($student, 'user')->for($this->otherCourse, 'course')->create();
    $ownAttempt = QuizAttempt::factory()->completed()->for($this->tenant)->for($student, 'user')->for($ownQuestionnaire, 'questionnaire')->create();
    $otherAttempt = QuizAttempt::factory()->completed()->for($this->tenant)->for($student, 'user')->for($otherQuestionnaire, 'questionnaire')->create();

    $this->getJson('/api/v1/instructor/assessment/results', $this->headers)
        ->assertJsonFragment(['attempt_id' => $ownAttempt->id])
        ->assertJsonMissing(['attempt_id' => $otherAttempt->id]);

    assertApiErrorEnvelope(
        $this->getJson('/api/v1/instructor/assessment/results/'.$otherAttempt->id, $this->headers),
        404,
        'not_found',
    );
});

it('freezes Questionnaire and Question after an in-progress attempt and rejects foreign composition', function (): void {
    $questionnaire = Questionnaire::factory()->course()->for($this->tenant)->create([
        'instructor_id' => $this->instructor->id,
        'quizable_type' => 'course',
        'quizable_id' => $this->ownCourse->id,
    ]);
    $question = QuizQuestion::factory()->for($this->tenant)->create(['instructor_id' => $this->instructor->id]);
    QuestionnaireQuestion::factory()->create([
        'questionnaire_id' => $questionnaire->id,
        'quiz_question_id' => $question->id,
        'sort_order' => 1,
    ]);
    QuizAttempt::factory()->for($this->tenant)->for($questionnaire, 'questionnaire')->create(['status' => 'in_progress']);

    $foreignQuestion = QuizQuestion::factory()->for($this->tenant)->create(['instructor_id' => $this->otherInstructor->id]);

    assertApiErrorEnvelope($this->postJson('/api/v1/instructor/assessment/questionnaires/'.$questionnaire->id.'/questions', [
        'question_ids' => [$foreignQuestion->id],
    ], $this->headers), 422, 'validation_error');
    assertApiErrorEnvelope($this->postJson('/api/v1/instructor/assessment/questionnaires/'.$questionnaire->id.'/questions', [
        'question_ids' => [$question->id],
    ], $this->headers), 422, 'validation_error');
    assertApiErrorEnvelope($this->patchJson('/api/v1/instructor/assessment/questionnaires/'.$questionnaire->id.'/questions/reorder', [
        'question_ids' => [$question->id],
    ], $this->headers), 422, 'validation_error');
    assertApiErrorEnvelope($this->deleteJson('/api/v1/instructor/assessment/questionnaires/'.$questionnaire->id.'/questions/'.$question->id, [], $this->headers), 422, 'validation_error');
    assertApiErrorEnvelope($this->deleteJson('/api/v1/instructor/assessment/questionnaires/'.$questionnaire->id, [], $this->headers), 422, 'validation_error');
    assertApiErrorEnvelope($this->patchJson('/api/v1/instructor/assessment/questions/'.$question->id, ['question' => 'frozen'], $this->headers), 422, 'validation_error');
    assertApiErrorEnvelope($this->deleteJson('/api/v1/instructor/assessment/questions/'.$question->id, [], $this->headers), 422, 'validation_error');
});

it('supports attached list, detach and reusing an own Question in another own Questionnaire', function (): void {
    $questionnaire = Questionnaire::factory()->course()->for($this->tenant)->create([
        'instructor_id' => $this->instructor->id,
        'quizable_type' => 'course',
        'quizable_id' => $this->ownCourse->id,
    ]);
    $secondQuestionnaire = Questionnaire::factory()->course()->for($this->tenant)->create([
        'instructor_id' => $this->instructor->id,
        'quizable_type' => 'course',
        'quizable_id' => $this->ownCourse->id,
    ]);
    $question = QuizQuestion::factory()->for($this->tenant)->create(['instructor_id' => $this->instructor->id]);

    $this->postJson('/api/v1/instructor/assessment/questionnaires/'.$questionnaire->id.'/questions', [
        'question_ids' => [$question->id],
    ], $this->headers)->assertOk();
    $this->getJson('/api/v1/instructor/assessment/questionnaires/'.$questionnaire->id.'/questions', $this->headers)
        ->assertOk()
        ->assertJsonPath('data.0.id', $question->id);
    $this->postJson('/api/v1/instructor/assessment/questionnaires/'.$secondQuestionnaire->id.'/questions', [
        'question_ids' => [$question->id],
    ], $this->headers)->assertOk();

    $this->deleteJson('/api/v1/instructor/assessment/questionnaires/'.$questionnaire->id.'/questions/'.$question->id, [], $this->headers)
        ->assertOk();
    expect(QuestionnaireQuestion::query()
        ->where('questionnaire_id', $questionnaire->id)
        ->where('quiz_question_id', $question->id)
        ->exists())->toBeFalse();
});

it('rejects a parent from another tenant defensively', function (): void {
    $foreignTenant = makeTenant();
    $foreignCourse = Course::factory()->for($foreignTenant)->create(['instructor_id' => $this->instructor->id]);

    assertApiErrorEnvelope(
        $this->postJson('/api/v1/instructor/assessment/questionnaires', [
            'title' => 'Cross tenant questionnaire',
            'type' => 'course',
            'quizable_type' => 'course',
            'quizable_id' => $foreignCourse->id,
        ], $this->headers),
        422,
        'validation_error',
    );
});
