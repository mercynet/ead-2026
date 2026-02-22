<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            // Core
            'core.users.list',
            'core.users.create',
            'core.users.view',
            'core.users.update',
            'core.users.delete',
            'core.users.update-self',
            'core.users.update-password',

            // Learning - Categories
            'learning.categories.list',
            'learning.categories.create',
            'learning.categories.view',
            'learning.categories.update',
            'learning.categories.delete',
            'learning.categories.system.manage',

            // Learning - Courses
            'learning.courses.list',
            'learning.courses.create',
            'learning.courses.view',
            'learning.courses.update',
            'learning.courses.delete',
            'learning.courses.publish',

            // Learning - Modules
            'learning.modules.list',
            'learning.modules.create',
            'learning.modules.view',
            'learning.modules.update',
            'learning.modules.delete',
            'learning.modules.reorder',

            // Learning - Lessons
            'learning.lessons.list',
            'learning.lessons.create',
            'learning.lessons.view',
            'learning.lessons.update',
            'learning.lessons.delete',

            // Learning - Enrollments
            'learning.enrollments.list',
            'learning.enrollments.create',
            'learning.enrollments.view',
            'learning.enrollments.update',
            'learning.enrollments.delete',

            // Learning - Progress
            'learning.progress.view',

            // Assessment - Questionnaires
            'assessment.questionnaires.list',
            'assessment.questionnaires.create',
            'assessment.questionnaires.view',
            'assessment.questionnaires.update',
            'assessment.questionnaires.delete',

            // Assessment - Questions
            'assessment.questions.list',
            'assessment.questions.create',
            'assessment.questions.view',
            'assessment.questions.update',
            'assessment.questions.delete',

            // Assessment - Attempts
            'assessment.attempts.list',
            'assessment.attempts.view',
            'assessment.attempts.answer',
            'assessment.attempts.finish',

            // Assessment - Certificates
            'assessment.certificates.list',
            'assessment.certificates.view',
            'assessment.certificates.revoke',
        ];

        foreach ($permissions as $permissionName) {
            Permission::query()->firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
