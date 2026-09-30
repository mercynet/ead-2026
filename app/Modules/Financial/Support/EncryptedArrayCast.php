<?php

namespace App\Modules\Financial\Support;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Encrypts JSON arrays while accepting plaintext JSON during a safe migration rollback.
 */
class EncryptedArrayCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        $payload = (string) $value;

        try {
            $payload = Crypt::decryptString($payload);
        } catch (DecryptException) {
            // A rolled-back migration may leave readable JSON text in the TEXT column.
        }

        return json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return [$key => null];
        }

        $payload = json_encode($value, JSON_THROW_ON_ERROR);

        return [$key => $this->isJsonColumn($model, $key) ? $payload : Crypt::encryptString($payload)];
    }

    private function isJsonColumn(Model $model, string $key): bool
    {
        return strtolower($model->getConnection()->getSchemaBuilder()->getColumnType($model->getTable(), $key)) === 'json';
    }
}
