<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->string('color', 7)->nullable()->after('normalized_name');
            $table->text('description')->nullable()->after('slug');
            $table->string('icon')->nullable()->after('description');
            $table->string('status')->default('active')->after('is_system');
            $table->boolean('is_featured')->default(false)->after('status');

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'status']);
            $table->dropColumn(['color', 'description', 'icon', 'status', 'is_featured']);
        });
    }
};
