<?php

use App\Enums\UserType;
use App\Models\Course;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('updates a course as admin', function (): void {
    $tenant = Tenant::query()->create([
        'name' => 'Tenant A',
        'domain' => 'tenant-a.local',
        'database' => null,
        'is_active' => true,
    ]);

    $this->seed([PermissionsSeeder::class, RolesSeeder::class]);

    $admin = User::query()->create([
        'tenant_id' => $tenant->id,
        'user_type' => UserType::Admin,
        'name' => 'Admin',
        'email' => 'admin-course@test.local',
        'password' => Hash::make('password123'),
    ]);
    $admin->assignRole('admin');

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Curso Original',
        'slug' => 'curso-original',
        'description' => 'Descrição original',
        'status' => 'draft',
        'price_cents' => 0,
        'is_featured' => false,
        'is_active' => true,
    ]);

    $token = $admin->createToken('admin-token')->plainTextToken;

    $this->patchJson('/api/v1/learning/courses/'.$course->id, [
        'title' => 'Curso Atualizado',
        'status' => 'published',
        'price_cents' => 9900,
    ], [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.title', 'Curso Atualizado')
        ->assertJsonPath('data.slug', 'curso-atualizado')
        ->assertJsonPath('data.status', 'published')
        ->assertJsonPath('data.price_cents', 9900);
});

it('deletes a course as admin', function (): void {
    $tenant = Tenant::query()->create([
        'name' => 'Tenant A',
        'domain' => 'tenant-a.local',
        'database' => null,
        'is_active' => true,
    ]);

    $this->seed([PermissionsSeeder::class, RolesSeeder::class]);

    $admin = User::query()->create([
        'tenant_id' => $tenant->id,
        'user_type' => UserType::Admin,
        'name' => 'Admin',
        'email' => 'admin-delete-course@test.local',
        'password' => Hash::make('password123'),
    ]);
    $admin->assignRole('admin');

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Curso para Deletar',
        'slug' => 'curso-para-deletar',
        'description' => 'Descrição',
        'status' => 'draft',
        'price_cents' => 0,
        'is_featured' => false,
        'is_active' => true,
    ]);

    $token = $admin->createToken('admin-token')->plainTextToken;

    $this->deleteJson('/api/v1/learning/courses/'.$course->id, [], [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])
        ->assertSuccessful()
        ->assertJsonPath('message', 'Course deleted successfully.');

    expect(Course::query()->find($course->id))->toBeNull();
});

it('forbids student from updating course', function (): void {
    $tenant = Tenant::query()->create([
        'name' => 'Tenant A',
        'domain' => 'tenant-a.local',
        'database' => null,
        'is_active' => true,
    ]);

    $this->seed([PermissionsSeeder::class, RolesSeeder::class]);

    $student = User::query()->create([
        'tenant_id' => $tenant->id,
        'user_type' => UserType::Student,
        'name' => 'Student',
        'email' => 'student-course@test.local',
        'password' => Hash::make('password123'),
    ]);
    $student->assignRole('student');

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Curso',
        'slug' => 'curso-student',
        'description' => 'Descrição',
        'status' => 'draft',
        'price_cents' => 0,
        'is_featured' => false,
        'is_active' => true,
    ]);

    $token = $student->createToken('student-token')->plainTextToken;

    $this->patchJson('/api/v1/learning/courses/'.$course->id, [
        'title' => 'Tentativa de Atualizar',
    ], [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])->assertForbidden();
});

it('allows developer to update any course', function (): void {
    $tenant = Tenant::query()->create([
        'name' => 'Tenant A',
        'domain' => 'tenant-a.local',
        'database' => null,
        'is_active' => true,
    ]);

    $this->seed([PermissionsSeeder::class, RolesSeeder::class]);

    $developer = User::query()->create([
        'tenant_id' => null,
        'user_type' => UserType::Developer,
        'name' => 'Developer',
        'email' => 'dev-course@test.local',
        'password' => Hash::make('password123'),
    ]);
    $developer->assignRole('developer');

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Curso Developer',
        'slug' => 'curso-developer',
        'description' => 'Descrição',
        'status' => 'draft',
        'price_cents' => 0,
        'is_featured' => false,
        'is_active' => true,
    ]);

    $token = $developer->createToken('dev-token')->plainTextToken;

    $this->patchJson('/api/v1/learning/courses/'.$course->id, [
        'title' => 'Curso Atualizado por Developer',
    ], [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.title', 'Curso Atualizado por Developer');
});
