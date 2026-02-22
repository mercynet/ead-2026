<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_progress', function (Blueprint $table): void {
            $table->unsignedInteger('progress_percentage')->default(0)->after('duration_watched');
            $table->boolean('is_completed')->default(false)->after('progress_percentage');
            $table->unsignedInteger('current_time_seconds')->default(0)->after('is_completed');
            $table->unsignedInteger('total_time_seconds')->default(0)->after('current_time_seconds');
            $table->timestamp('last_watched_at')->nullable()->after('completed_at');
            $table->foreignId('user_id')->after('tenant_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->after('lesson_id')->constrained('courses')->cascadeOnDelete();

            $table->index(['user_id', 'course_id']);
            $table->index(['lesson_id', 'progress_percentage']);
        });

        Schema::table('lesson_progress', function (Blueprint $table): void {
            $table->renameColumn('duration_watched', 'time_spent_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_progress', function (Blueprint $table): void {
            $table->renameColumn('time_spent_seconds', 'duration_watched');
        });

        Schema::table('lesson_progress', function (Blueprint $table): void {
            $table->dropColumn([
                'progress_percentage',
                'is_completed',
                'current_time_seconds',
                'total_time_seconds',
                'last_watched_at',
            ]);
            $table->dropForeign(['user_id']);
            $table->dropForeign(['course_id']);
            $table->dropColumn(['user_id', 'course_id']);
            $table->dropIndex(['user_id', 'course_id']);
            $table->dropIndex(['lesson_id', 'progress_percentage']);
        });
    }
};
