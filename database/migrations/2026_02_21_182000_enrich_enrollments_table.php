<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table): void {
            $table->timestamp('enrolled_at')->nullable()->after('course_id');
            $table->timestamp('completed_at')->nullable()->after('expires_at');
            $table->renameColumn('expires_at', 'access_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table): void {
            $table->dropColumn(['enrolled_at', 'completed_at']);
            $table->renameColumn('access_expires_at', 'expires_at');
        });
    }
};
