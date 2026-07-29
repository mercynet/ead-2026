<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_price_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('old_price_cents');
            $table->unsignedInteger('new_price_cents');
            $table->timestamp('changed_at');

            $table->index(['tenant_id', 'course_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_price_histories');
    }
};
