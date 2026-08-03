<?php

use App\Modules\Core\Enums\UserType;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Learning\Models\Category;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\CourseModule;
use App\Modules\Learning\Models\Enrollment;
use App\Modules\Learning\Models\Lesson;
use App\Modules\Learning\Models\RatingStats;
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
    $courseFreeFeatured->categories()->attach($categoryTech->id, ['tenant_id' => $tenant->id, 'sort_order' => 1, 'is_featured' => false]);
    $courseFreeFeatured->categories()->attach($systemCategory->id, ['tenant_id' => $tenant->id, 'sort_order' => 2, 'is_featured' => false]);

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
    $coursePaid->categories()->attach($categoryDesign->id, ['tenant_id' => $tenant->id, 'sort_order' => 1, 'is_featured' => false]);

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
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment(['slug' => 'laravel-zero-to-hero'])
        ->assertJsonFragment(['slug' => 'ui-premium'])
        ->assertJsonFragment(['slug' => 'desenvolvimento-de-software'])
        ->assertJsonPath('data.0.rating_stats', null)
        ->assertJsonMissing(['slug' => 'draft-course']);

    $this->getJson('/api/v1/learning/catalog/courses?category=tech&is_free=1&is_featured=1', [
        'X-Tenant-ID' => (string) $tenant->id,
    ])
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonFragment(['slug' => 'laravel-zero-to-hero'])
        ->assertJsonMissing(['slug' => 'ui-premium']);
});

it('orders top rated courses by tenant and exposes rating stats', function (): void {
    $tenant = Tenant::query()->create([
        'name' => 'Tenant A',
        'domain' => 'tenant-a.local',
        'database' => null,
        'is_active' => true,
    ]);

    $courseHighA = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'High A',
        'slug' => 'high-a',
        'description' => 'Course description',
        'status' => 'published',
        'price_cents' => 0,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    $courseHighB = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'High B',
        'slug' => 'high-b',
        'description' => 'Course description',
        'status' => 'published',
        'price_cents' => 0,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    $courseLow = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Low',
        'slug' => 'low',
        'description' => 'Course description',
        'status' => 'published',
        'price_cents' => 0,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    RatingStats::query()->create([
        'tenant_id' => $tenant->id,
        'rateable_type' => $courseHighA->getMorphClass(),
        'rateable_id' => $courseHighA->id,
        'average_stars' => 4.80,
        'total_ratings' => 10,
        'five_stars' => 8,
        'four_stars' => 2,
        'three_stars' => 0,
        'two_stars' => 0,
        'one_star' => 0,
        'likes_count' => 9,
        'dislikes_count' => 1,
        'last_rated_at' => now(),
    ]);

    RatingStats::query()->create([
        'tenant_id' => $tenant->id,
        'rateable_type' => $courseHighB->getMorphClass(),
        'rateable_id' => $courseHighB->id,
        'average_stars' => 4.80,
        'total_ratings' => 10,
        'five_stars' => 6,
        'four_stars' => 2,
        'three_stars' => 0,
        'two_stars' => 0,
        'one_star' => 0,
        'likes_count' => 8,
        'dislikes_count' => 0,
        'last_rated_at' => now(),
    ]);

    RatingStats::query()->create([
        'tenant_id' => $tenant->id,
        'rateable_type' => $courseLow->getMorphClass(),
        'rateable_id' => $courseLow->id,
        'average_stars' => 4.20,
        'total_ratings' => 3,
        'five_stars' => 2,
        'four_stars' => 1,
        'three_stars' => 0,
        'two_stars' => 0,
        'one_star' => 0,
        'likes_count' => 3,
        'dislikes_count' => 0,
        'last_rated_at' => now(),
    ]);

    $response = $this->getJson('/api/v1/learning/catalog/courses?sort=top_rated', [
        'X-Tenant-ID' => (string) $tenant->id,
    ])
        ->assertSuccessful();

    $response
        ->assertJsonPath('data.0.slug', 'high-a')
        ->assertJsonPath('data.1.slug', 'high-b')
        ->assertJsonPath('data.2.slug', 'low')
        ->assertJsonPath('data.0.rating_stats.average_stars', 4.8)
        ->assertJsonPath('data.0.rating_stats.total_ratings', 10);

    $this->getJson('/api/v1/learning/catalog/courses?sort=top_rated&min_ratings=5', [
        'X-Tenant-ID' => (string) $tenant->id,
    ])
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment(['slug' => 'high-a'])
        ->assertJsonFragment(['slug' => 'high-b'])
        ->assertJsonMissing(['slug' => 'low']);
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
    Permission::query()->firstOrCreate(['name' => 'learning.courses.list', 'guard_name' => 'web']);
    Role::query()->firstOrCreate(['name' => 'student', 'guard_name' => 'web'])
        ->givePermissionTo('learning.courses.list');
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
        ->assertJsonCount(1, 'data')
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
    $course->categories()->attach($category->id, ['tenant_id' => $tenant->id, 'sort_order' => 1, 'is_featured' => false]);

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
        ->assertJsonPath('data.slug', 'course-detail')
        ->assertJsonPath('data.modules.0.title', 'Module 1')
        ->assertJsonPath('data.modules.0.lessons.0.title', 'Lesson 1')
        ->assertJsonPath('data.categories.0.slug', 'tech');
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

it('allows developer to list courses without tenant context', function (): void {
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

    Role::query()->firstOrCreate(['name' => 'developer', 'guard_name' => 'web']);

    $developer = User::query()->create([
        'tenant_id' => null,
        'user_type' => UserType::Developer,
        'name' => 'Developer',
        'email' => 'developer-courses-no-tenant@platform.test',
        'password' => Hash::make('password123'),
    ]);
    $developer->assignRole('developer');

    Course::query()->create([
        'tenant_id' => $tenantA->id,
        'title' => 'Curso Tenant A',
        'slug' => 'curso-tenant-a',
        'description' => 'A',
        'status' => 'published',
        'price_cents' => 0,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    Course::query()->create([
        'tenant_id' => $tenantB->id,
        'title' => 'Curso Tenant B',
        'slug' => 'curso-tenant-b',
        'description' => 'B',
        'status' => 'published',
        'price_cents' => 0,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    $token = $developer->createToken('developer-courses-no-tenant-token')->plainTextToken;

    $this->getJson('/api/v1/learning/catalog/courses', [
        'Authorization' => 'Bearer '.$token,
    ])
        ->assertSuccessful()
        ->assertJsonFragment(['slug' => 'curso-tenant-a'])
        ->assertJsonFragment(['slug' => 'curso-tenant-b']);
});
