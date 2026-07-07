<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates catalog and learning tables', function (): void {
    expect(Schema::hasTable('categories'))->toBeTrue();
    expect(Schema::hasTable('courses'))->toBeTrue();
    expect(Schema::hasTable('course_modules'))->toBeTrue();
    expect(Schema::hasTable('lessons'))->toBeTrue();
    expect(Schema::hasTable('lesson_media'))->toBeTrue();
    expect(Schema::hasTable('course_materials'))->toBeTrue();
    expect(Schema::hasTable('material_downloads'))->toBeTrue();
    expect(Schema::hasTable('material_stats'))->toBeTrue();
    expect(Schema::hasTable('enrollments'))->toBeTrue();
    expect(Schema::hasTable('category_course'))->toBeTrue();
});

it('stores lesson media with tenant and access payload fields', function (): void {
    expect(Schema::hasColumns('lesson_media', [
        'tenant_id',
        'lesson_id',
        'media_type',
        'provider',
        'provider_ref',
        'url',
        'content',
        'duration_seconds',
        'sort_order',
        'is_active',
        'metadata',
    ]))->toBeTrue();
});

it('stores course materials with tenant and ownership fields', function (): void {
    expect(Schema::hasColumns('course_materials', [
        'tenant_id',
        'course_id',
        'instructor_id',
        'file_path',
    ]))->toBeTrue();
});

it('stores material downloads with tenant, actor and audit fields', function (): void {
    expect(Schema::hasColumns('material_downloads', [
        'tenant_id',
        'course_material_id',
        'user_id',
        'ip_address',
        'user_agent',
        'downloaded_at',
    ]))->toBeTrue();
});

it('stores material stats rollups per material', function (): void {
    expect(Schema::hasColumns('material_stats', [
        'tenant_id',
        'course_material_id',
        'total_downloads',
        'downloads_today',
        'downloads_week',
        'downloads_month',
        'last_downloaded_at',
    ]))->toBeTrue();
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
