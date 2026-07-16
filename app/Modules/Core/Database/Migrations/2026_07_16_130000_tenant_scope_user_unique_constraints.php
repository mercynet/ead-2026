<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identidade tenant-scoped (docs/specs/10-core-identity/subspecs/users.md): troca
 * o unique GLOBAL de email/cpf por compostos (tenant_id, …). A mesma pessoa pode
 * existir em tenants distintos como registros independentes. NULLs em tenant_id
 * (developers/landlord) não colidem — o unique composto os ignora.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['email']);
            $table->dropUnique(['cpf']);
            $table->unique(['tenant_id', 'email'], 'users_tenant_email_unique');
            $table->unique(['tenant_id', 'cpf'], 'users_tenant_cpf_unique');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_tenant_email_unique');
            $table->dropUnique('users_tenant_cpf_unique');
            $table->unique('email');
            $table->unique('cpf');
        });
    }
};
