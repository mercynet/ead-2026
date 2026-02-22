<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->foreignId('instructor_id')->nullable()->after('tenant_id')->constrained('users')->nullOnDelete();
            $table->unsignedInteger('order')->default(0)->after('instructor_id');
            $table->string('short_description')->nullable()->after('description');
            $table->text('target_audience')->nullable()->after('short_description');
            $table->text('requirements')->nullable()->after('target_audience');
            $table->text('what_you_will_learn')->nullable()->after('requirements');
            $table->text('what_you_will_build')->nullable()->after('what_you_will_learn');
            $table->string('thumbnail')->nullable()->after('status');
            $table->string('banner')->nullable()->after('thumbnail');
            $table->string('level')->default('beginner')->after('banner');
            $table->unsignedInteger('duration_hours')->default(0)->after('price_cents');
            $table->boolean('is_active')->default(true)->after('is_featured');
            $table->dateTime('vehiculation_started_at')->nullable()->after('published_at');
            $table->dateTime('vehiculation_ended_at')->nullable()->after('vehiculation_started_at');

            $table->index(['tenant_id', 'level']);
            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->dropForeign(['instructor_id']);
            $table->dropIndex(['tenant_id', 'level']);
            $table->dropIndex(['tenant_id', 'is_active']);
            $table->dropIndex(['tenant_id', 'order']);

            $table->dropColumn([
                'instructor_id',
                'order',
                'short_description',
                'target_audience',
                'requirements',
                'what_you_will_learn',
                'what_you_will_build',
                'thumbnail',
                'banner',
                'level',
                'duration_hours',
                'is_active',
                'vehiculation_started_at',
                'vehiculation_ended_at',
            ]);
        });
    }
};
