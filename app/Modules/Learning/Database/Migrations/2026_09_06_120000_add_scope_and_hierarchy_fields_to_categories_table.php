<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->unsignedBigInteger('tenant_key')
                ->virtualAs('coalesce(`tenant_id`, 0)')
                ->after('tenant_id');
            $table->string('path', 255)->nullable()->after('parent_id');
            $table->unsignedInteger('depth')->default(0)->after('path');
            $table->unique(['tenant_key', 'normalized_name'], 'categories_tenant_key_normalized_name_unique');
            $table->index('path', 'categories_path_index');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->dropIndex('categories_path_index');
            $table->dropUnique('categories_tenant_key_normalized_name_unique');
            $table->dropColumn(['tenant_key', 'path', 'depth']);
        });
    }
};
