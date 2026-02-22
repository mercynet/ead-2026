<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table): void {
            $table->string('slug')->nullable()->after('title');
            $table->text('short_description')->nullable()->after('slug');
            $table->text('description')->nullable()->after('short_description');
            $table->string('video_path')->nullable()->after('description');
            $table->string('status')->default('draft')->after('video_path');
            $table->string('thumbnail')->nullable()->after('status');
            $table->string('content_type')->nullable()->after('thumbnail');
            $table->string('duration')->nullable()->after('content_type');
            $table->boolean('is_active')->default(true)->after('is_free');
            $table->dateTime('published_at')->nullable()->after('is_active');
            $table->softDeletes();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table): void {
            $table->dropUnique(['tenant_id', 'slug']);
            $table->dropIndex(['tenant_id', 'status']);
            $table->dropIndex(['tenant_id', 'is_active']);

            $table->dropColumn([
                'slug',
                'short_description',
                'description',
                'video_path',
                'status',
                'thumbnail',
                'content_type',
                'duration',
                'is_active',
                'published_at',
                'deleted_at',
            ]);
        });
    }
};
