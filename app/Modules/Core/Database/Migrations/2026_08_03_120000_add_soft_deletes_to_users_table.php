<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soft delete de usuário: exclusão precisa preservar o histórico (matrículas,
 * orders, trilha de auditoria) que aponta para o registro.
 *
 * O unique de email permanece sobre a linha, sem `deleted_at`: e-mail de usuário
 * excluído segue reservado no tenant. Afrouxar o índice para reaproveitar o
 * e-mail abriria brecha de colisão entre usuários ativos, o que é pior.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
