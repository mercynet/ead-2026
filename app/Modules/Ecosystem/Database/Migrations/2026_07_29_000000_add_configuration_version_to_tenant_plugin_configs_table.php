<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_plugin_configs', function (Blueprint $table): void {
            $table->string('configuration_version', 64)->nullable()->after('enabled');
        });
        DB::statement("UPDATE tenant_plugin_configs SET configuration_version = CONCAT('legacy-', id) WHERE configuration_version IS NULL");
        if (DB::table('tenant_plugin_configs')->whereNull('configuration_version')->exists()) {
            throw new RuntimeException('Configuration version backfill did not complete.');
        }
        Schema::table('tenant_plugin_configs', function (Blueprint $table): void {
            $table->string('configuration_version', 64)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('tenant_plugin_configs', function (Blueprint $table): void {
            $table->dropColumn('configuration_version');
        });
    }
};
