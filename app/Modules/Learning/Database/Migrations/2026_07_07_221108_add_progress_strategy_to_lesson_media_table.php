<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_media', function (Blueprint $table): void {
            $table->string('progress_strategy')
                ->default('80_percent')
                ->after('duration_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_media', function (Blueprint $table): void {
            $table->dropColumn('progress_strategy');
        });
    }
};
