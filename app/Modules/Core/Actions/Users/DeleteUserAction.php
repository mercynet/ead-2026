<?php

namespace App\Modules\Core\Actions\Users;

use App\Modules\Core\Models\User;
use Illuminate\Database\DatabaseManager;

class DeleteUserAction
{
    public function __construct(
        private readonly DatabaseManager $database,
    ) {}

    /**
     * Soft delete + revogação das sessões Sanctum na mesma transação: usuário
     * excluído com token vivo continuaria autenticando até o token expirar.
     */
    public function handle(User $user): void
    {
        $this->database->transaction(function () use ($user): void {
            $user->tokens()->delete();
            $user->delete();
        });
    }
}
