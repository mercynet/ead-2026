<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'id']);
        });

        Schema::table('category_course', function (Blueprint $table): void {
            $table->unsignedInteger('sort_order')->nullable()->after('course_id');
            $table->boolean('is_featured')->default(false)->after('sort_order');
        });

        $currentCourseId = null;
        $sortOrder = 0;

        foreach (DB::table('category_course')->orderBy('course_id')->orderBy('category_id')->cursor() as $pivot) {
            if ($pivot->course_id !== $currentCourseId) {
                $currentCourseId = $pivot->course_id;
                $sortOrder = 0;
            }

            DB::table('category_course')
                ->where('tenant_id', $pivot->tenant_id)
                ->where('category_id', $pivot->category_id)
                ->where('course_id', $pivot->course_id)
                ->update(['sort_order' => ++$sortOrder]);
        }

        Schema::table('category_course', function (Blueprint $table): void {
            $table->unsignedInteger('sort_order')->nullable(false)->change();
            $table->dropUnique(['tenant_id', 'category_id', 'course_id']);
            $table->dropForeign(['course_id']);
            $table->unique(['course_id', 'category_id']);
            $table->unique(['course_id', 'sort_order']);
            $table->index(['tenant_id', 'category_id', 'course_id']);
            $table->index(['course_id', 'sort_order', 'category_id']);
            $table->foreign(['tenant_id', 'course_id'])
                ->references(['tenant_id', 'id'])
                ->on('courses')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('category_course', function (Blueprint $table): void {
            $table->dropForeign(['tenant_id', 'course_id']);
            $table->dropUnique(['course_id', 'category_id']);
            $table->dropUnique(['course_id', 'sort_order']);
            $table->dropIndex(['tenant_id', 'category_id', 'course_id']);
            $table->dropIndex(['course_id', 'sort_order', 'category_id']);
            $table->dropColumn(['sort_order', 'is_featured']);
            $table->unique(['tenant_id', 'category_id', 'course_id']);
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
        });

        Schema::table('courses', function (Blueprint $table): void {
            $table->dropUnique(['tenant_id', 'id']);
        });
    }
};
