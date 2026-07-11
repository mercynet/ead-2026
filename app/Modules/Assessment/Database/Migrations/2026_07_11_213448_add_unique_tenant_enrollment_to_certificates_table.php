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
        Schema::table('certificates', function (Blueprint $table) {
            $table->unique(['tenant_id', 'enrollment_id']);
            $table->dropIndex(['tenant_id', 'enrollment_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->index(['tenant_id', 'enrollment_id']);
            $table->dropUnique(['tenant_id', 'enrollment_id']);
        });
    }
};
