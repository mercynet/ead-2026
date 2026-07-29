<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_plugin_config_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_plugin_config_id')->constrained()->cascadeOnDelete();
            $table->string('configuration_version', 64);
            $table->text('config')->nullable();
            $table->timestamps();

            $table->unique(
                ['tenant_plugin_config_id', 'configuration_version'],
                'tenant_plugin_config_revisions_identity_unique',
            );
        });

        DB::table('tenant_plugin_configs')->orderBy('id')->each(function (object $config): void {
            DB::table('tenant_plugin_config_revisions')->insert([
                'tenant_plugin_config_id' => $config->id,
                'configuration_version' => $config->configuration_version,
                'config' => $config->config,
                'created_at' => $config->created_at,
                'updated_at' => $config->updated_at,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_plugin_config_revisions');
    }
};
