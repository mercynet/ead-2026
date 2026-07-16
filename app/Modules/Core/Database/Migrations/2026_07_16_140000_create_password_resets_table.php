<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recuperação de senha tenant-scoped. Token opaco persistido só como SHA-256;
 * expira; uso único (used_at). A tabela `password_reset_tokens` default do
 * Laravel (chaveada por email global) não serve ao modelo tenant-scoped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_resets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('email');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'email'], 'password_resets_tenant_email_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_resets');
    }
};
