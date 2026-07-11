<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->foreignId('course_id')->nullable()->after('enrollment_id')->constrained('courses')->nullOnDelete();
            $table->index(['tenant_id', 'course_id']);
        });

        DB::table('certificates')->whereNull('course_id')->update([
            'course_id' => DB::raw('(select course_id from enrollments where enrollments.id = certificates.enrollment_id)'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'course_id']);
            $table->dropConstrainedForeignId('course_id');
        });
    }
};
