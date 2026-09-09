<?php

use App\Modules\Assessment\Actions\Certificate\IssueCertificateAction;
use App\Modules\Assessment\Listeners\IssueCertificateOnCourseCompletedListener;
use App\Modules\Core\Models\User;
use App\Modules\Learning\Events\CourseCompletedEvent;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\Enrollment;
use Illuminate\Support\Facades\Log;

uses(Tests\TestCase::class);

it('logs certificate failure metadata without the exception object', function (): void {
    $enrollment = new Enrollment(['tenant_id' => 21]);
    $enrollment->id = 34;
    $user = new User;
    $user->id = 55;
    $course = new Course;
    $course->id = 89;
    $event = new CourseCompletedEvent($enrollment, $user, $course);
    $action = Mockery::mock(IssueCertificateAction::class);
    $exception = new RuntimeException('sensitive downstream details');
    $action->shouldReceive('handle')->once()->with($event)->andThrow($exception);

    Log::shouldReceive('error')->once()->with('Certificate issuance failed after course completion.', Mockery::on(function (array $context): bool {
        return $context === [
            'tenant_id' => 21,
            'enrollment_id' => 34,
            'course_id' => 89,
            'exception_class' => RuntimeException::class,
        ];
    }));

    (new IssueCertificateOnCourseCompletedListener($action))->handle($event);
});
