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
        Schema::table('questionnaires', function (Blueprint $table) {
            $table->unsignedBigInteger('quizable_id')->nullable()->change();
            $table->string('quizable_type', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('questionnaires', function (Blueprint $table) {
            $table->unsignedBigInteger('quizable_id')->change();
            $table->string('quizable_type', 255)->change();
        });
    }
};
