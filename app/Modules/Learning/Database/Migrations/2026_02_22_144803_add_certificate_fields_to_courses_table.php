<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->boolean('certificate_enabled')->default(false)->after('is_featured');
            $table->unsignedTinyInteger('certificate_min_progress')->default(100)->after('certificate_enabled');
            $table->boolean('certificate_requires_quiz')->default(false)->after('certificate_min_progress');
            $table->unsignedTinyInteger('certificate_min_score')->default(70)->after('certificate_requires_quiz');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn([
                'certificate_enabled',
                'certificate_min_progress',
                'certificate_requires_quiz',
                'certificate_min_score',
            ]);
        });
    }
};
