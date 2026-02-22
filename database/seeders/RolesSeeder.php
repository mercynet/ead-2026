<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roles = [
            [
                'name' => 'developer',
                'scope' => 'global',
                'tenant_id' => null,
                'permissions' => [
                    'core.users.list',
                    'core.users.create',
                    'core.users.view',
                    'core.users.update',
                    'core.users.delete',
                    'core.users.update-self',
                    'core.users.update-password',
                    'learning.categories.list',
                    'learning.categories.create',
                    'learning.categories.view',
                    'learning.categories.update',
                    'learning.categories.delete',
                    'learning.categories.system.manage',
                    'learning.courses.list',
                    'learning.courses.create',
                    'learning.courses.view',
                    'learning.courses.update',
                    'learning.courses.delete',
                    'learning.courses.publish',
                    'learning.modules.list',
                    'learning.modules.create',
                    'learning.modules.view',
                    'learning.modules.update',
                    'learning.modules.delete',
                    'learning.modules.reorder',
                    'learning.lessons.list',
                    'learning.lessons.create',
                    'learning.lessons.view',
                    'learning.lessons.update',
                    'learning.lessons.delete',
                    'learning.enrollments.list',
                    'learning.enrollments.create',
                    'learning.enrollments.view',
                    'learning.enrollments.update',
                    'learning.enrollments.delete',
                    'learning.progress.view',
                    'assessment.questionnaires.list',
                    'assessment.questionnaires.create',
                    'assessment.questionnaires.view',
                    'assessment.questionnaires.update',
                    'assessment.questionnaires.delete',
                    'assessment.questions.list',
                    'assessment.questions.create',
                    'assessment.questions.view',
                    'assessment.questions.update',
                    'assessment.questions.delete',
                    'assessment.attempts.list',
                    'assessment.attempts.view',
                    'assessment.attempts.answer',
                    'assessment.attempts.finish',
                    'assessment.certificates.list',
                    'assessment.certificates.view',
                    'assessment.certificates.revoke',
                ],
            ],
            [
                'name' => 'admin',
                'scope' => 'global',
                'tenant_id' => null,
                'permissions' => [
                    'core.users.list',
                    'core.users.create',
                    'core.users.view',
                    'core.users.update',
                    'core.users.delete',
                    'core.users.update-self',
                    'core.users.update-password',
                    'learning.categories.list',
                    'learning.categories.create',
                    'learning.categories.view',
                    'learning.categories.update',
                    'learning.categories.delete',
                    'learning.courses.list',
                    'learning.courses.create',
                    'learning.courses.view',
                    'learning.courses.update',
                    'learning.courses.delete',
                    'learning.courses.publish',
                    'learning.modules.list',
                    'learning.modules.create',
                    'learning.modules.view',
                    'learning.modules.update',
                    'learning.modules.delete',
                    'learning.modules.reorder',
                    'learning.lessons.list',
                    'learning.lessons.create',
                    'learning.lessons.view',
                    'learning.lessons.update',
                    'learning.lessons.delete',
                    'learning.enrollments.list',
                    'learning.enrollments.create',
                    'learning.enrollments.view',
                    'learning.enrollments.update',
                    'learning.enrollments.delete',
                    'learning.progress.view',
                    'assessment.questionnaires.list',
                    'assessment.questionnaires.create',
                    'assessment.questionnaires.view',
                    'assessment.questionnaires.update',
                    'assessment.questionnaires.delete',
                    'assessment.questions.list',
                    'assessment.questions.create',
                    'assessment.questions.view',
                    'assessment.questions.update',
                    'assessment.questions.delete',
                    'assessment.attempts.list',
                    'assessment.attempts.view',
                    'assessment.certificates.list',
                    'assessment.certificates.view',
                    'assessment.certificates.revoke',
                ],
            ],
            [
                'name' => 'instructor',
                'scope' => 'global',
                'tenant_id' => null,
                'permissions' => [
                    'core.users.show',
                    'core.users.update-self',
                    'core.users.update-password',
                    'learning.categories.list',
                    'learning.courses.list',
                    'learning.courses.create',
                    'learning.courses.view',
                    'learning.courses.update',
                    'learning.courses.delete',
                    'learning.courses.publish',
                    'learning.modules.list',
                    'learning.modules.create',
                    'learning.modules.view',
                    'learning.modules.update',
                    'learning.modules.delete',
                    'learning.modules.reorder',
                    'learning.lessons.list',
                    'learning.lessons.create',
                    'learning.lessons.view',
                    'learning.lessons.update',
                    'learning.lessons.delete',
                    'learning.enrollments.list',
                    'learning.enrollments.view',
                    'learning.progress.view',
                    'assessment.questionnaires.list',
                    'assessment.questionnaires.create',
                    'assessment.questionnaires.view',
                    'assessment.questionnaires.update',
                    'assessment.questionnaires.delete',
                    'assessment.questions.list',
                    'assessment.questions.create',
                    'assessment.questions.view',
                    'assessment.questions.update',
                    'assessment.questions.delete',
                    'assessment.attempts.list',
                    'assessment.attempts.view',
                    'assessment.certificates.list',
                    'assessment.certificates.view',
                ],
            ],
            [
                'name' => 'student',
                'scope' => 'global',
                'tenant_id' => null,
                'permissions' => [
                    'core.users.show',
                    'core.users.update-self',
                    'core.users.update-password',
                    'learning.categories.list',
                    'learning.courses.list',
                    'learning.courses.show',
                ],
            ],
        ];

        foreach ($roles as $roleData) {
            $role = Role::query()->firstOrCreate([
                'name' => $roleData['name'],
                'guard_name' => 'web',
            ]);

            $role->update([
                'scope' => $roleData['scope'],
                'tenant_id' => $roleData['tenant_id'],
            ]);

            foreach ($roleData['permissions'] as $permissionName) {
                \Spatie\Permission\Models\Permission::firstOrCreate([
                    'name' => $permissionName,
                    'guard_name' => 'web',
                ]);
            }

            $role->syncPermissions($roleData['permissions']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
