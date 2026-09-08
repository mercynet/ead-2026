<?php

it('keeps the unreleased capability gate in the Learning publication boundary', function (): void {
    $readinessPath = base_path('app/Modules/Learning/Services/CourseCommercialReadiness.php');
    $publishActionPath = base_path('app/Modules/Learning/Actions/Course/PublishCourseAction.php');
    $adminControllerPath = base_path('app/Modules/Learning/Http/Controllers/Admin/CourseController.php');

    $readiness = file_get_contents($readinessPath);
    $publishAction = file_get_contents($publishActionPath);
    $adminController = file_get_contents($adminControllerPath);

    expect($readiness)->toBeString()
        ->and($publishAction)->toBeString()
        ->and($adminController)->toBeString()
        ->and($readiness)->toContain('final class CourseCommercialReadiness')
        ->toContain('public function assertPublishable(Course $course): void')
        ->not->toContain('App\\Modules\\Assessment\\Models')
        ->and($publishAction)->toContain('CourseCommercialReadiness')
        ->toContain('commercialReadiness->assertPublishable($course)')
        ->not->toContain('ApiContext')
        ->not->toContain('User')
        ->and($adminController)->not->toContain('CourseCommercialReadiness')
        ->not->toContain('certificate_enabled')
        ->not->toContain('certificate_requires_quiz');
});
