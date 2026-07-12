<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plugins', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('name');
            $table->string('capability_key', 100)->unique();
            $table->string('kind', 30)->default('feature');
            $table->string('status', 20)->default('draft');
            $table->string('visibility', 20)->default('public');
            $table->string('tier', 20)->default('free');
            $table->boolean('is_curated')->default(false);
            $table->string('directory_name')->nullable();
            $table->string('short_description', 255)->nullable();
            $table->text('long_description')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('default_locale', 10)->nullable();
            $table->string('support_url')->nullable();
            $table->string('docs_url')->nullable();
            $table->timestamps();

            $table->index(['kind', 'status'], 'plugins_kind_status_index');
            $table->index(['status', 'visibility'], 'plugins_status_visibility_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugins');
    }
};
