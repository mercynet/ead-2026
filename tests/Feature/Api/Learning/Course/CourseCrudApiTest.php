<?php

use App\Modules\Core\Enums\UserType;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Learning\Models\Course;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

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

it('publishes immediately when created with published status', function (): void {
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
        ->assertJsonPath('data.status', 'published');

    $course = Course::query()->where('title', 'Curso Publicado')->first();
    expect($course->published_at)->not->toBeNull();
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

    $this->getJson('/api/v1/learning/courses/'.$course->id, [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.id', $course->id)
        ->assertJsonPath('data.title', 'Curso Draft')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonStructure(['data' => ['id', 'title', 'slug', 'status', 'categories', 'modules']]);
});

it('allows student to view a course (broad view permission)', function (): void {
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

    $this->getJson('/api/v1/learning/courses/'.$course->id, [
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

    $this->getJson('/api/v1/learning/courses/999999', [
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

    $this->getJson('/api/v1/learning/courses/'.$course->id, [
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

    $this->getJson('/api/v1/learning/courses/'.$course->id, [
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
