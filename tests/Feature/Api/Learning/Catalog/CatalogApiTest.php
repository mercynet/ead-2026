<?php

use App\Models\Category;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('lists only published tenant courses and supports filters', function (): void {
    $tenant = Tenant::query()->create([
        'name' => 'Tenant A',
        'domain' => 'tenant-a.local',
        'database' => null,
        'is_active' => true,
    ]);

    $categoryTech = Category::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Tech',
        'slug' => 'tech',
        'normalized_name' => 'tech',
        'is_system' => false,
    ]);

    $categoryDesign = Category::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Design',
        'slug' => 'design',
        'normalized_name' => 'design',
        'is_system' => false,
    ]);

    $systemCategory = Category::query()->create([
        'tenant_id' => null,
        'parent_id' => null,
        'name' => 'Desenvolvimento de Software',
        'slug' => 'desenvolvimento-de-software',
        'normalized_name' => 'desenvolvimento de software',
        'is_system' => true,
    ]);

    $courseFreeFeatured = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Laravel Zero to Hero',
        'slug' => 'laravel-zero-to-hero',
        'description' => 'Course description',
        'status' => 'published',
        'price_cents' => 0,
        'access_days' => 90,
        'is_featured' => true,
    ]);
    $courseFreeFeatured->categories()->attach($categoryTech->id, ['tenant_id' => $tenant->id]);
    $courseFreeFeatured->categories()->attach($systemCategory->id, ['tenant_id' => $tenant->id]);

    $coursePaid = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'UI Premium',
        'slug' => 'ui-premium',
        'description' => 'Course description',
        'status' => 'published',
        'price_cents' => 19900,
        'access_days' => 180,
        'is_featured' => false,
    ]);
    $coursePaid->categories()->attach($categoryDesign->id, ['tenant_id' => $tenant->id]);

    Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Draft Course',
        'slug' => 'draft-course',
        'description' => 'Draft',
        'status' => 'draft',
        'price_cents' => 0,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    $this->getJson('/api/v1/learning/catalog/courses', [
        'X-Tenant-ID' => (string) $tenant->id,
    ])
        ->assertSuccessful()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonFragment(['slug' => 'laravel-zero-to-hero'])
        ->assertJsonFragment(['slug' => 'ui-premium'])
        ->assertJsonFragment(['slug' => 'desenvolvimento-de-software'])
        ->assertJsonMissing(['slug' => 'draft-course']);

    $this->getJson('/api/v1/learning/catalog/courses?category=tech&is_free=1&is_featured=1', [
        'X-Tenant-ID' => (string) $tenant->id,
    ])
        ->assertSuccessful()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonFragment(['slug' => 'laravel-zero-to-hero'])
        ->assertJsonMissing(['slug' => 'ui-premium']);
});

it('hides purchased courses for authenticated user', function (): void {
    $tenant = Tenant::query()->create([
        'name' => 'Tenant A',
        'domain' => 'tenant-a.local',
        'database' => null,
        'is_active' => true,
    ]);

    $student = User::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Student',
        'email' => 'student@tenant-a.test',
        'password' => Hash::make('password123'),
    ]);
    Permission::query()->firstOrCreate(['name' => 'learning.catalog.courses.list', 'guard_name' => 'web']);
    Role::query()->firstOrCreate(['name' => 'student', 'guard_name' => 'web'])
        ->givePermissionTo('learning.catalog.courses.list');
    $student->assignRole('student');

    $purchasedCourse = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Purchased Course',
        'slug' => 'purchased-course',
        'description' => 'Course description',
        'status' => 'published',
        'price_cents' => 1000,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    $visibleCourse = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Visible Course',
        'slug' => 'visible-course',
        'description' => 'Course description',
        'status' => 'published',
        'price_cents' => 2000,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    Enrollment::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'course_id' => $purchasedCourse->id,
        'status' => 'active',
        'progress_percentage' => 30,
        'expires_at' => now()->addDays(10),
    ]);

    $token = $student->createToken('student-token')->plainTextToken;

    $this->getJson('/api/v1/learning/catalog/courses', [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])
        ->assertSuccessful()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonMissing(['slug' => 'purchased-course'])
        ->assertJsonFragment(['slug' => $visibleCourse->slug]);
});

it('shows published course by slug with modules and lessons', function (): void {
    $tenant = Tenant::query()->create([
        'name' => 'Tenant A',
        'domain' => 'tenant-a.local',
        'database' => null,
        'is_active' => true,
    ]);

    $category = Category::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Tech',
        'slug' => 'tech',
        'normalized_name' => 'tech',
        'is_system' => false,
    ]);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Course Detail',
        'slug' => 'course-detail',
        'description' => 'Full detail',
        'status' => 'published',
        'price_cents' => 9900,
        'access_days' => 365,
        'is_featured' => true,
    ]);
    $course->categories()->attach($category->id, ['tenant_id' => $tenant->id]);

    $module = CourseModule::query()->create([
        'tenant_id' => $tenant->id,
        'course_id' => $course->id,
        'title' => 'Module 1',
        'sort_order' => 1,
    ]);

    Lesson::query()->create([
        'tenant_id' => $tenant->id,
        'course_module_id' => $module->id,
        'title' => 'Lesson 1',
        'sort_order' => 1,
        'is_free' => true,
    ]);

    $this->getJson('/api/v1/learning/catalog/courses/course-detail', [
        'X-Tenant-ID' => (string) $tenant->id,
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.course.slug', 'course-detail')
        ->assertJsonPath('data.course.modules.0.title', 'Module 1')
        ->assertJsonPath('data.course.modules.0.lessons.0.title', 'Lesson 1')
        ->assertJsonPath('data.course.categories.0.slug', 'tech');
});

it('does not show draft course detail for catalog endpoint', function (): void {
    $tenant = Tenant::query()->create([
        'name' => 'Tenant A',
        'domain' => 'tenant-a.local',
        'database' => null,
        'is_active' => true,
    ]);

    Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Draft Course',
        'slug' => 'draft-course',
        'description' => 'Draft',
        'status' => 'draft',
        'price_cents' => 0,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    $this->getJson('/api/v1/learning/catalog/courses/draft-course', [
        'X-Tenant-ID' => (string) $tenant->id,
    ])->assertNotFound();
});
