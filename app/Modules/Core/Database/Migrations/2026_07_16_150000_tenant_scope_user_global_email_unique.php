<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fecha a brecha do unique composto (tenant_id, email): no MySQL, valores NULL
 * em tenant_id NÃO colidem no índice único, então dois developers/landlord
 * globais (tenant_id NULL) com o MESMO email conviviam — e LoginAction escolhia
 * um `->first()` arbitrário.
 *
 * Substitui o unique por (tenant_scope, email), onde tenant_scope é uma coluna
 * GERADA = COALESCE(tenant_id, 0). Como tenant.id é auto-increment começando em 1,
 * o 0 é um sentinela seguro para "identidade global": todos os globais caem no
 * mesmo escopo e passam a colidir corretamente por email. Para tenants reais o
 * comportamento é idêntico ao unique anterior (tenant_scope == tenant_id).
 *
 * cpf fica de fora de propósito: developers têm cpf NULL, e normalizar NULL→0
 * faria todos os globais sem cpf colidirem entre si.
 *
 * A coluna é VIRTUAL (não STORED): a FK de tenant_id usa ON DELETE SET NULL, e o
 * MySQL proíbe uma coluna gerada STORED que dependa de uma coluna base sob essa
 * ação referencial (erro 1215). InnoDB indexa colunas virtuais normalmente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_tenant_email_unique');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedBigInteger('tenant_scope')
                ->virtualAs('COALESCE(tenant_id, 0)')
                ->after('tenant_id');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->unique(['tenant_scope', 'email'], 'users_tenant_scope_email_unique');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_tenant_scope_email_unique');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('tenant_scope');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'email'], 'users_tenant_email_unique');
        });
    }
};
