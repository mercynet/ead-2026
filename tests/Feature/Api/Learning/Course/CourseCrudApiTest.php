<?php

use App\Modules\Core\Enums\UserType;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Learning\Models\Course;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('creates a course as admin', function (): void {
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
        'email' => 'admin-create-course@test.local',
        'password' => Hash::make('password123'),
    ]);
    $admin->assignRole('admin');

    $token = $admin->createToken('admin-token')->plainTextToken;

    $this->postJson('/api/v1/learning/courses', [
        'title' => 'Novo Curso',
        'description' => 'Descrição do novo curso',
        'price_cents' => 9900,
        'access_days' => 365,
    ], [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Novo Curso')
        ->assertJsonPath('data.slug', 'novo-curso')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.price_cents', 9900);

    $course = Course::query()->where('title', 'Novo Curso')->first();
    expect($course)->not->toBeNull();
    expect((int) $course->tenant_id)->toBe($tenant->id);
    expect((int) $course->instructor_id)->toBe($admin->id);
});

it('creates a course as instructor', function (): void {
    $tenant = Tenant::query()->create([
        'name' => 'Tenant A',
        'domain' => 'tenant-a.local',
        'database' => null,
        'is_active' => true,
    ]);

    $this->seed([PermissionsSeeder::class, RolesSeeder::class]);

    $instructor = User::query()->create([
        'tenant_id' => $tenant->id,
        'user_type' => UserType::Instructor,
        'name' => 'Instructor',
        'email' => 'instructor-create-course@test.local',
        'password' => Hash::make('password123'),
    ]);
    $instructor->assignRole('instructor');

    $token = $instructor->createToken('instructor-token')->plainTextToken;

    $this->postJson('/api/v1/learning/courses', [
        'title' => 'Curso do Instrutor',
    ], [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Curso do Instrutor')
        ->assertJsonPath('data.status', 'draft');
});

it('creates courses always as draft', function (): void {
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
        'email' => 'admin-create-published@test.local',
        'password' => Hash::make('password123'),
    ]);
    $admin->assignRole('admin');

    $token = $admin->createToken('admin-token')->plainTextToken;

    $this->postJson('/api/v1/learning/courses', [
        'title' => 'Curso Publicado',
        'status' => 'published',
    ], [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft');

    $course = Course::query()->where('title', 'Curso Publicado')->first();
    expect($course->published_at)->toBeNull();
});

it('forbids student from creating a course', function (): void {
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
        'email' => 'student-create-course@test.local',
        'password' => Hash::make('password123'),
    ]);
    $student->assignRole('student');

    $token = $student->createToken('student-token')->plainTextToken;

    $this->postJson('/api/v1/learning/courses', [
        'title' => 'Tentativa de Criar',
    ], [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])->assertForbidden();
});

it('requires authentication to create a course', function (): void {
    $tenant = Tenant::query()->create([
        'name' => 'Tenant A',
        'domain' => 'tenant-a.local',
        'database' => null,
        'is_active' => true,
    ]);

    $this->postJson('/api/v1/learning/courses', [
        'title' => 'Sem Auth',
    ], [
        'X-Tenant-ID' => (string) $tenant->id,
    ])->assertUnauthorized();
});

it('allows an instructor owner to preview their draft course', function (): void {
    $tenant = Tenant::query()->create([
        'name' => 'Tenant A',
        'domain' => 'tenant-a.local',
        'database' => null,
        'is_active' => true,
    ]);

    $this->seed([PermissionsSeeder::class, RolesSeeder::class]);

    $instructor = User::query()->create([
        'tenant_id' => $tenant->id,
        'user_type' => UserType::Instructor,
        'name' => 'Instructor',
        'email' => 'instructor-preview-owner@test.local',
        'password' => Hash::make('password123'),
    ]);
    $instructor->assignRole('instructor');

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'instructor_id' => $instructor->id,
        'title' => 'Curso Draft',
        'slug' => 'curso-draft',
        'description' => 'Descrição',
        'status' => 'draft',
        'price_cents' => 0,
        'is_featured' => false,
        'is_active' => true,
    ]);

    $token = $instructor->createToken('instructor-token')->plainTextToken;

    $this->getJson('/api/v1/learning/courses/'.$course->id.'/preview', [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])
        ->assertOk()
        ->assertJsonPath('data.id', $course->id)
        ->assertJsonPath('data.status', 'draft');
});

it('allows an admin to preview a draft course', function (): void {
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
        'email' => 'admin-preview-course@test.local',
        'password' => Hash::make('password123'),
    ]);
    $admin->assignRole('admin');

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'instructor_id' => $admin->id,
        'title' => 'Curso Draft',
        'slug' => 'curso-draft-admin',
        'description' => 'Descrição',
        'status' => 'draft',
        'price_cents' => 0,
        'is_featured' => false,
        'is_active' => true,
    ]);

    $token = $admin->createToken('admin-token')->plainTextToken;

    $this->getJson('/api/v1/learning/courses/'.$course->id.'/preview', [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])
        ->assertOk()
        ->assertJsonPath('data.id', $course->id);
});

it('allows a developer to preview a draft course', function (): void {
    $tenant = Tenant::query()->create([
        'name' => 'Tenant A',
        'domain' => 'tenant-a.local',
        'database' => null,
        'is_active' => true,
    ]);

    $this->seed([PermissionsSeeder::class, RolesSeeder::class]);

    $developer = User::query()->create([
        'tenant_id' => $tenant->id,
        'user_type' => UserType::Developer,
        'name' => 'Developer',
        'email' => 'developer-preview-course@test.local',
        'password' => Hash::make('password123'),
    ]);
    $developer->assignRole('developer');

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'instructor_id' => $developer->id,
        'title' => 'Curso Draft',
        'slug' => 'curso-draft-dev',
        'description' => 'Descrição',
        'status' => 'draft',
        'price_cents' => 0,
        'is_featured' => false,
        'is_active' => true,
    ]);

    $token = $developer->createToken('developer-token')->plainTextToken;

    $this->getJson('/api/v1/learning/courses/'.$course->id.'/preview', [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])
        ->assertOk()
        ->assertJsonPath('data.id', $course->id);
});

it('forbids a student from previewing a draft course', function (): void {
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
        'email' => 'student-preview-course@test.local',
        'password' => Hash::make('password123'),
    ]);
    $student->assignRole('student');

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'instructor_id' => $student->id,
        'title' => 'Curso Draft',
        'slug' => 'curso-draft-student',
        'description' => 'Descrição',
        'status' => 'draft',
        'price_cents' => 0,
        'is_featured' => false,
        'is_active' => true,
    ]);

    $token = $student->createToken('student-token')->plainTextToken;

    $this->getJson('/api/v1/learning/courses/'.$course->id.'/preview', [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])->assertForbidden();
});

it('forbids a non-owner instructor from previewing a draft course', function (): void {
    $tenant = Tenant::query()->create([
        'name' => 'Tenant A',
        'domain' => 'tenant-a.local',
        'database' => null,
        'is_active' => true,
    ]);

    $this->seed([PermissionsSeeder::class, RolesSeeder::class]);

    $owner = User::query()->create([
        'tenant_id' => $tenant->id,
        'user_type' => UserType::Instructor,
        'name' => 'Owner',
        'email' => 'owner-preview-course@test.local',
        'password' => Hash::make('password123'),
    ]);
    $owner->assignRole('instructor');

    $otherInstructor = User::query()->create([
        'tenant_id' => $tenant->id,
        'user_type' => UserType::Instructor,
        'name' => 'Other',
        'email' => 'other-preview-course@test.local',
        'password' => Hash::make('password123'),
    ]);
    $otherInstructor->assignRole('instructor');

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'instructor_id' => $owner->id,
        'title' => 'Curso Draft',
        'slug' => 'curso-draft-owner',
        'description' => 'Descrição',
        'status' => 'draft',
        'price_cents' => 0,
        'is_featured' => false,
        'is_active' => true,
    ]);

    $token = $otherInstructor->createToken('instructor-token')->plainTextToken;

    $this->getJson('/api/v1/learning/courses/'.$course->id.'/preview', [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])->assertForbidden();
});

it('returns 404 for a course from another tenant when previewing', function (): void {
    $tenantA = Tenant::query()->create([
        'name' => 'Tenant A',
        'domain' => 'tenant-a.local',
        'database' => null,
        'is_active' => true,
    ]);
    $tenantB = Tenant::query()->create([
        'name' => 'Tenant B',
        'domain' => 'tenant-b.local',
        'database' => null,
        'is_active' => true,
    ]);

    $this->seed([PermissionsSeeder::class, RolesSeeder::class]);

    $admin = User::query()->create([
        'tenant_id' => $tenantA->id,
        'user_type' => UserType::Admin,
        'name' => 'Admin',
        'email' => 'admin-preview-foreign@test.local',
        'password' => Hash::make('password123'),
    ]);
    $admin->assignRole('admin');

    $course = Course::query()->create([
        'tenant_id' => $tenantB->id,
        'instructor_id' => $admin->id,
        'title' => 'Foreign course',
        'slug' => 'foreign-course',
        'description' => 'Descrição',
        'status' => 'draft',
        'price_cents' => 0,
        'is_featured' => false,
        'is_active' => true,
    ]);

    $token = $admin->createToken('admin-token')->plainTextToken;

    $this->getJson('/api/v1/learning/courses/'.$course->id.'/preview', [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenantA->id,
    ])->assertNotFound();
});

it('requires authentication to preview a course', function (): void {
    $tenant = Tenant::query()->create([
        'name' => 'Tenant A',
        'domain' => 'tenant-a.local',
        'database' => null,
        'is_active' => true,
    ]);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Curso Draft',
        'slug' => 'curso-draft-auth',
        'description' => 'Descrição',
        'status' => 'draft',
        'price_cents' => 0,
        'is_featured' => false,
        'is_active' => true,
    ]);

    $this->getJson('/api/v1/learning/courses/'.$course->id.'/preview', [
        'X-Tenant-ID' => (string) $tenant->id,
    ])->assertUnauthorized();
});

it('validates required title when creating a course', function (): void {
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
        'email' => 'admin-validate-course@test.local',
        'password' => Hash::make('password123'),
    ]);
    $admin->assignRole('admin');

    $token = $admin->createToken('admin-token')->plainTextToken;

    $this->postJson('/api/v1/learning/courses', [
        'description' => 'Sem título',
    ], [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])->assertStatus(422);
});

it('shows a course as admin', function (): void {
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
        'email' => 'admin-show-course@test.local',
        'password' => Hash::make('password123'),
    ]);
    $admin->assignRole('admin');

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Curso Draft',
        'slug' => 'curso-draft',
        'description' => 'Descrição',
        'status' => 'draft',
        'price_cents' => 0,
        'is_featured' => false,
        'is_active' => true,
    ]);

    $token = $admin->createToken('admin-token')->plainTextToken;

    $this->getJson('/api/v1/admin/courses/'.$course->id, [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.id', $course->id)
        ->assertJsonPath('data.title', 'Curso Draft')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonStructure(['data' => ['id', 'title', 'slug', 'status', 'categories', 'modules']]);
});

it('forbids student from the admin area', function (): void {
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
        'email' => 'student-show-course@test.local',
        'password' => Hash::make('password123'),
    ]);
    $student->assignRole('student');

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Curso',
        'slug' => 'curso-show-student',
        'description' => 'Descrição',
        'status' => 'draft',
        'price_cents' => 0,
        'is_featured' => false,
        'is_active' => true,
    ]);

    $token = $student->createToken('student-token')->plainTextToken;

    $this->getJson('/api/v1/admin/courses/'.$course->id, [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])
        ->assertForbidden()
        ->assertJsonPath('errors.0.code', 'area_forbidden');
});

it('forbids instructor from the admin area', function (): void {
    $tenant = Tenant::query()->create([
        'name' => 'Tenant A',
        'domain' => 'tenant-a.local',
        'database' => null,
        'is_active' => true,
    ]);

    $this->seed([PermissionsSeeder::class, RolesSeeder::class]);

    $instructor = User::query()->create([
        'tenant_id' => $tenant->id,
        'user_type' => UserType::Instructor,
        'name' => 'Instructor',
        'email' => 'instructor-admin-area@test.local',
        'password' => Hash::make('password123'),
    ]);
    $instructor->assignRole('instructor');

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Curso',
        'slug' => 'curso-show-instructor',
        'description' => 'Descrição',
        'status' => 'draft',
        'price_cents' => 0,
        'is_featured' => false,
        'is_active' => true,
    ]);

    $token = $instructor->createToken('instructor-token')->plainTextToken;

    $this->getJson('/api/v1/admin/courses/'.$course->id, [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])
        ->assertForbidden()
        ->assertJsonPath('errors.0.code', 'area_forbidden');
});

it('allows developer into the admin area by hierarchy', function (): void {
    $tenant = Tenant::query()->create([
        'name' => 'Tenant A',
        'domain' => 'tenant-a.local',
        'database' => null,
        'is_active' => true,
    ]);

    $this->seed([PermissionsSeeder::class, RolesSeeder::class]);

    $developer = User::query()->create([
        'tenant_id' => $tenant->id,
        'user_type' => UserType::Developer,
        'name' => 'Developer',
        'email' => 'developer-admin-area@test.local',
        'password' => Hash::make('password123'),
    ]);
    $developer->assignRole('developer');

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Curso Dev',
        'slug' => 'curso-show-developer',
        'description' => 'Descrição',
        'status' => 'draft',
        'price_cents' => 0,
        'is_featured' => false,
        'is_active' => true,
    ]);

    $token = $developer->createToken('developer-token')->plainTextToken;

    $this->getJson('/api/v1/admin/courses/'.$course->id, [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.id', $course->id);
});

it('returns 404 viewing a missing course', function (): void {
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
        'email' => 'admin-show-missing@test.local',
        'password' => Hash::make('password123'),
    ]);
    $admin->assignRole('admin');

    $token = $admin->createToken('admin-token')->plainTextToken;

    $this->getJson('/api/v1/admin/courses/999999', [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])->assertNotFound();
});

it('isolates course view across tenants', function (): void {
    $tenantA = Tenant::query()->create([
        'name' => 'Tenant A',
        'domain' => 'tenant-a.local',
        'database' => null,
        'is_active' => true,
    ]);
    $tenantB = Tenant::query()->create([
        'name' => 'Tenant B',
        'domain' => 'tenant-b.local',
        'database' => null,
        'is_active' => true,
    ]);

    $this->seed([PermissionsSeeder::class, RolesSeeder::class]);

    $course = Course::query()->create([
        'tenant_id' => $tenantA->id,
        'title' => 'Curso A',
        'slug' => 'curso-a',
        'description' => 'Descrição',
        'status' => 'draft',
        'price_cents' => 0,
        'is_featured' => false,
        'is_active' => true,
    ]);

    $adminB = User::query()->create([
        'tenant_id' => $tenantB->id,
        'user_type' => UserType::Admin,
        'name' => 'Admin B',
        'email' => 'admin-b-show@test.local',
        'password' => Hash::make('password123'),
    ]);
    $adminB->assignRole('admin');

    $token = $adminB->createToken('admin-token')->plainTextToken;

    $this->getJson('/api/v1/admin/courses/'.$course->id, [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenantB->id,
    ])->assertNotFound();
});

it('requires authentication to view a course', function (): void {
    $tenant = Tenant::query()->create([
        'name' => 'Tenant A',
        'domain' => 'tenant-a.local',
        'database' => null,
        'is_active' => true,
    ]);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Curso',
        'slug' => 'curso-noauth',
        'description' => 'Descrição',
        'status' => 'draft',
        'price_cents' => 0,
        'is_featured' => false,
        'is_active' => true,
    ]);

    $this->getJson('/api/v1/admin/courses/'.$course->id, [
        'X-Tenant-ID' => (string) $tenant->id,
    ])->assertUnauthorized();
});

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
        'price_cents' => 9900,
    ], [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.title', 'Curso Atualizado')
        ->assertJsonPath('data.slug', 'curso-atualizado')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.price_cents', 9900);
});

it('publishes and unpublishes a course as admin', function (): void {
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
        'email' => 'admin-publish-course@test.local',
        'password' => Hash::make('password123'),
    ]);
    $admin->assignRole('admin');

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Curso para Publicar',
        'slug' => 'curso-para-publicar',
        'description' => 'Descrição',
        'status' => 'draft',
        'price_cents' => 0,
        'is_featured' => false,
        'is_active' => true,
    ]);

    $token = $admin->createToken('admin-token')->plainTextToken;

    $this->postJson('/api/v1/admin/courses/'.$course->id.'/publish', [], [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'published');

    $course->refresh();
    expect($course->published_at)->not->toBeNull();
    $publishedAt = $course->published_at;

    $this->postJson('/api/v1/admin/courses/'.$course->id.'/unpublish', [], [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'draft');

    $course->refresh();
    expect($course->published_at)->toEqual($publishedAt);
});

it('forbids publish without publish permission in admin area', function (): void {
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
        'email' => 'admin-no-publish@test.local',
        'password' => Hash::make('password123'),
    ]);

    $role = Role::query()->create([
        'name' => 'admin-no-publish',
        'guard_name' => 'web',
    ]);

    $role->syncPermissions(collect(config('permissions'))
        ->keys()
        ->reject(fn (string $permission): bool => $permission === 'learning.courses.publish')
        ->all());

    $admin->assignRole('admin-no-publish');

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Curso Sem Publish',
        'slug' => 'curso-sem-publish',
        'description' => 'Descrição',
        'status' => 'draft',
        'price_cents' => 0,
        'is_featured' => false,
        'is_active' => true,
    ]);

    $token = $admin->createToken('admin-token')->plainTextToken;

    $this->postJson('/api/v1/admin/courses/'.$course->id.'/publish', [], [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])->assertForbidden();
});

it('requires authentication to publish a course', function (): void {
    $tenant = Tenant::query()->create([
        'name' => 'Tenant A',
        'domain' => 'tenant-a.local',
        'database' => null,
        'is_active' => true,
    ]);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Curso Sem Auth',
        'slug' => 'curso-sem-auth',
        'description' => 'Descrição',
        'status' => 'draft',
        'price_cents' => 0,
        'is_featured' => false,
        'is_active' => true,
    ]);

    $this->postJson('/api/v1/admin/courses/'.$course->id.'/publish', [], [
        'X-Tenant-ID' => (string) $tenant->id,
    ])->assertUnauthorized();
});

it('returns 404 publishing a course from another tenant', function (): void {
    $tenantA = Tenant::query()->create([
        'name' => 'Tenant A',
        'domain' => 'tenant-a.local',
        'database' => null,
        'is_active' => true,
    ]);
    $tenantB = Tenant::query()->create([
        'name' => 'Tenant B',
        'domain' => 'tenant-b.local',
        'database' => null,
        'is_active' => true,
    ]);

    $this->seed([PermissionsSeeder::class, RolesSeeder::class]);

    $admin = User::query()->create([
        'tenant_id' => $tenantB->id,
        'user_type' => UserType::Admin,
        'name' => 'Admin B',
        'email' => 'admin-b-publish@test.local',
        'password' => Hash::make('password123'),
    ]);
    $admin->assignRole('admin');

    $course = Course::query()->create([
        'tenant_id' => $tenantA->id,
        'title' => 'Curso A',
        'slug' => 'curso-a-publish',
        'description' => 'Descrição',
        'status' => 'draft',
        'price_cents' => 0,
        'is_featured' => false,
        'is_active' => true,
    ]);

    $token = $admin->createToken('admin-token')->plainTextToken;

    $this->postJson('/api/v1/admin/courses/'.$course->id.'/publish', [], [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenantB->id,
    ])->assertNotFound();
});

it('rejects archived course publish and unpublish', function (): void {
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
        'email' => 'admin-archived@test.local',
        'password' => Hash::make('password123'),
    ]);
    $admin->assignRole('admin');

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Curso Arquivado',
        'slug' => 'curso-arquivado',
        'description' => 'Descrição',
        'status' => 'archived',
        'price_cents' => 0,
        'is_featured' => false,
        'is_active' => true,
    ]);

    $token = $admin->createToken('admin-token')->plainTextToken;

    $this->postJson('/api/v1/admin/courses/'.$course->id.'/publish', [], [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])
        ->assertStatus(422)
        ->assertJsonPath('errors.0.message', 'Archived courses cannot be published.');

    $this->postJson('/api/v1/admin/courses/'.$course->id.'/unpublish', [], [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])
        ->assertStatus(422)
        ->assertJsonPath('errors.0.message', 'Archived courses cannot be unpublished.');
});

it('blocks patch bypass from publishing without publish permission', function (): void {
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
        'email' => 'admin-patch-bypass@test.local',
        'password' => Hash::make('password123'),
    ]);
    $admin->assignRole('admin');
    $admin->revokePermissionTo('learning.courses.publish');

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Curso Bypass',
        'slug' => 'curso-bypass',
        'description' => 'Descrição',
        'status' => 'draft',
        'price_cents' => 0,
        'is_featured' => false,
        'is_active' => true,
    ]);

    $token = $admin->createToken('admin-token')->plainTextToken;

    $this->patchJson('/api/v1/learning/courses/'.$course->id, [
        'status' => 'published',
    ], [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'draft');

    $course->refresh();
    expect($course->status)->toBe('draft');
    expect($course->published_at)->toBeNull();
});

it('forbids non-admin roles from admin publish surface', function (): void {
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
        'email' => 'student-publish@test.local',
        'password' => Hash::make('password123'),
    ]);
    $student->assignRole('student');

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Curso',
        'slug' => 'curso-publish-forbidden',
        'description' => 'Descrição',
        'status' => 'draft',
        'price_cents' => 0,
        'is_featured' => false,
        'is_active' => true,
    ]);

    $token = $student->createToken('student-token')->plainTextToken;

    $this->postJson('/api/v1/admin/courses/'.$course->id.'/publish', [], [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])->assertForbidden();
});

it('allows developer to publish through the admin surface', function (): void {
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
        'email' => 'developer-publish@test.local',
        'password' => Hash::make('password123'),
    ]);
    $developer->assignRole('developer');

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Curso Dev',
        'slug' => 'curso-dev-publish',
        'description' => 'Descrição',
        'status' => 'draft',
        'price_cents' => 0,
        'is_featured' => false,
        'is_active' => true,
    ]);

    $token = $developer->createToken('dev-token')->plainTextToken;

    $this->postJson('/api/v1/admin/courses/'.$course->id.'/publish', [], [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'published');
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

it('rejects access_days outside the closed preset list', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-x.local']);
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);

    assertApiErrorEnvelope(
        $this->postJson('/api/v1/learning/courses', [
            'title' => 'Curso Preset Inválido',
            'access_days' => 15,
        ], $headers),
        422,
        'validation_error'
    );
});

it('accepts access_days 0 as the lifetime preset', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-x.local']);
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);

    $this->postJson('/api/v1/learning/courses', [
        'title' => 'Curso Vitalício',
        'access_days' => 0,
    ], $headers)->assertCreated();

    expect(Course::query()->where('title', 'Curso Vitalício')->firstOrFail()->access_days)->toBe(0);
});
