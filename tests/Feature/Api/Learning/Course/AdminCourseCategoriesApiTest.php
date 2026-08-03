<?php

use App\Modules\Core\Enums\UserType;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Learning\Models\Category;
use App\Modules\Learning\Models\Course;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * @return array{0: Tenant, 1: Course, 2: array<string, string>}
 */
function adminCourseCategoriesContext(UserType $userType = UserType::Admin, bool $withRole = true): array
{
    $tenant = Tenant::factory()->create();
    test()->seed([PermissionsSeeder::class, RolesSeeder::class]);

    $user = User::factory()->create([
        'tenant_id' => $userType === UserType::Developer ? null : $tenant->id,
        'user_type' => $userType,
    ]);

    if ($withRole) {
        $user->assignRole($userType->value);
    }

    $course = Course::factory()->for($tenant)->create();

    return [$tenant, $course, [
        'Authorization' => 'Bearer '.$user->createToken('sync-categories-token')->plainTextToken,
        'X-Tenant-ID' => (string) $tenant->id,
    ]];
}

/**
 * @return list<array{category_id: int, sort_order: int, is_featured: int, tenant_id: int}>
 */
function coursePivotRows(Course $course): array
{
    return DB::table('category_course')
        ->where('course_id', $course->id)
        ->orderBy('sort_order')
        ->get(['category_id', 'sort_order', 'is_featured', 'tenant_id'])
        ->map(fn (object $row): array => (array) $row)
        ->all();
}

it('syncs course categories in payload order', function (): void {
    [$tenant, $course, $headers] = adminCourseCategoriesContext();

    $first = Category::factory()->for($tenant)->create();
    $second = Category::factory()->for($tenant)->create();

    $this->putJson('/api/v1/admin/courses/'.$course->id.'/categories', [
        'categories' => [
            ['id' => $second->id, 'is_featured' => true],
            ['id' => $first->id],
        ],
    ], $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.categories.0.id', $second->id)
        ->assertJsonPath('data.categories.1.id', $first->id);

    expect(coursePivotRows($course))->toBe([
        ['category_id' => $second->id, 'sort_order' => 1, 'is_featured' => 1, 'tenant_id' => $tenant->id],
        ['category_id' => $first->id, 'sort_order' => 2, 'is_featured' => 0, 'tenant_id' => $tenant->id],
    ]);
});

it('replaces the previous category set on a second sync', function (): void {
    [$tenant, $course, $headers] = adminCourseCategoriesContext();

    $stale = Category::factory()->for($tenant)->create();
    $kept = Category::factory()->for($tenant)->create();

    $this->putJson('/api/v1/admin/courses/'.$course->id.'/categories', [
        'categories' => [['id' => $stale->id], ['id' => $kept->id]],
    ], $headers)->assertSuccessful();

    $this->putJson('/api/v1/admin/courses/'.$course->id.'/categories', [
        'categories' => [['id' => $kept->id]],
    ], $headers)->assertSuccessful();

    expect(coursePivotRows($course))->toBe([
        ['category_id' => $kept->id, 'sort_order' => 1, 'is_featured' => 0, 'tenant_id' => $tenant->id],
    ]);
});

it('is idempotent for the same payload', function (): void {
    [$tenant, $course, $headers] = adminCourseCategoriesContext();

    $category = Category::factory()->for($tenant)->create();
    $payload = ['categories' => [['id' => $category->id, 'is_featured' => true]]];

    $this->putJson('/api/v1/admin/courses/'.$course->id.'/categories', $payload, $headers)->assertSuccessful();
    $this->putJson('/api/v1/admin/courses/'.$course->id.'/categories', $payload, $headers)->assertSuccessful();

    expect(coursePivotRows($course))->toBe([
        ['category_id' => $category->id, 'sort_order' => 1, 'is_featured' => 1, 'tenant_id' => $tenant->id],
    ]);
});

it('clears every category link with an empty list', function (): void {
    [$tenant, $course, $headers] = adminCourseCategoriesContext();

    $category = Category::factory()->for($tenant)->create();

    $this->putJson('/api/v1/admin/courses/'.$course->id.'/categories', [
        'categories' => [['id' => $category->id]],
    ], $headers)->assertSuccessful();

    $this->putJson('/api/v1/admin/courses/'.$course->id.'/categories', [
        'categories' => [],
    ], $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.categories', []);

    expect(coursePivotRows($course))->toBe([]);
});

it('accepts system categories', function (): void {
    [$tenant, $course, $headers] = adminCourseCategoriesContext();

    $systemCategory = Category::factory()->system()->create();

    $this->putJson('/api/v1/admin/courses/'.$course->id.'/categories', [
        'categories' => [['id' => $systemCategory->id]],
    ], $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.categories.0.id', $systemCategory->id);

    expect(coursePivotRows($course))->toBe([
        ['category_id' => $systemCategory->id, 'sort_order' => 1, 'is_featured' => 0, 'tenant_id' => $tenant->id],
    ]);
});

it('rejects a category owned by another tenant', function (): void {
    [, $course, $headers] = adminCourseCategoriesContext();

    $foreignCategory = Category::factory()->for(Tenant::factory()->create())->create();

    $this->putJson('/api/v1/admin/courses/'.$course->id.'/categories', [
        'categories' => [['id' => $foreignCategory->id]],
    ], $headers)
        ->assertUnprocessable()
        ->assertJsonPath('errors.0.code', 'validation_error');

    expect(coursePivotRows($course))->toBe([]);
});

it('rejects a missing category', function (): void {
    [, $course, $headers] = adminCourseCategoriesContext();

    $this->putJson('/api/v1/admin/courses/'.$course->id.'/categories', [
        'categories' => [['id' => 999999]],
    ], $headers)
        ->assertUnprocessable()
        ->assertJsonPath('errors.0.code', 'validation_error');

    expect(coursePivotRows($course))->toBe([]);
});

it('rejects a soft deleted category', function (): void {
    [$tenant, $course, $headers] = adminCourseCategoriesContext();

    $category = Category::factory()->for($tenant)->create();
    $category->delete();

    $this->putJson('/api/v1/admin/courses/'.$course->id.'/categories', [
        'categories' => [['id' => $category->id]],
    ], $headers)
        ->assertUnprocessable()
        ->assertJsonPath('errors.0.code', 'validation_error');

    expect(coursePivotRows($course))->toBe([]);
});

it('rejects repeated categories in the payload', function (): void {
    [$tenant, $course, $headers] = adminCourseCategoriesContext();

    $category = Category::factory()->for($tenant)->create();

    $this->putJson('/api/v1/admin/courses/'.$course->id.'/categories', [
        'categories' => [['id' => $category->id], ['id' => $category->id]],
    ], $headers)
        ->assertUnprocessable()
        ->assertJsonPath('errors.0.code', 'validation_error');

    expect(coursePivotRows($course))->toBe([]);
});

it('keeps the previous set when the payload is invalid', function (): void {
    [$tenant, $course, $headers] = adminCourseCategoriesContext();

    $category = Category::factory()->for($tenant)->create();

    $this->putJson('/api/v1/admin/courses/'.$course->id.'/categories', [
        'categories' => [['id' => $category->id]],
    ], $headers)->assertSuccessful();

    $this->putJson('/api/v1/admin/courses/'.$course->id.'/categories', [
        'categories' => [['id' => $category->id], ['id' => 999999]],
    ], $headers)->assertUnprocessable();

    expect(coursePivotRows($course))->toBe([
        ['category_id' => $category->id, 'sort_order' => 1, 'is_featured' => 0, 'tenant_id' => $tenant->id],
    ]);
});

it('hides a course from another tenant', function (): void {
    [, , $headers] = adminCourseCategoriesContext();

    $foreignCourse = Course::factory()->for(Tenant::factory()->create())->create();

    $this->putJson('/api/v1/admin/courses/'.$foreignCourse->id.'/categories', [
        'categories' => [],
    ], $headers)
        ->assertNotFound()
        ->assertJsonPath('errors.0.code', 'not_found');
});

it('forbids non admin personas from the admin surface', function (UserType $userType): void {
    [$tenant, $course, $headers] = adminCourseCategoriesContext($userType);

    $category = Category::factory()->for($tenant)->create();

    $this->putJson('/api/v1/admin/courses/'.$course->id.'/categories', [
        'categories' => [['id' => $category->id]],
    ], $headers)
        ->assertForbidden()
        ->assertJsonPath('errors.0.code', 'area_forbidden');

    expect(coursePivotRows($course))->toBe([]);
})->with([
    'developer' => [UserType::Developer],
    'instructor' => [UserType::Instructor],
    'student' => [UserType::Student],
]);

it('forbids an admin without the course update permission', function (): void {
    [$tenant, $course, $headers] = adminCourseCategoriesContext(UserType::Admin, withRole: false);

    $category = Category::factory()->for($tenant)->create();

    $this->putJson('/api/v1/admin/courses/'.$course->id.'/categories', [
        'categories' => [['id' => $category->id]],
    ], $headers)
        ->assertForbidden()
        ->assertJsonPath('errors.0.code', 'access_denied');

    expect(coursePivotRows($course))->toBe([]);
});
