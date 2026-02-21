<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates catalog and learning tables', function (): void {
    expect(Schema::hasTable('categories'))->toBeTrue();
    expect(Schema::hasTable('courses'))->toBeTrue();
    expect(Schema::hasTable('course_modules'))->toBeTrue();
    expect(Schema::hasTable('lessons'))->toBeTrue();
    expect(Schema::hasTable('enrollments'))->toBeTrue();
    expect(Schema::hasTable('category_course'))->toBeTrue();
});

it('applies hierarchical and system-aware fields to categories', function (): void {
    expect(Schema::hasColumns('categories', [
        'tenant_id',
        'parent_id',
        'name',
        'slug',
        'normalized_name',
        'is_system',
        'deleted_at',
    ]))->toBeTrue();
});

it('stores tenant context in category course pivot', function (): void {
    expect(Schema::hasColumns('category_course', [
        'tenant_id',
        'category_id',
        'course_id',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});
