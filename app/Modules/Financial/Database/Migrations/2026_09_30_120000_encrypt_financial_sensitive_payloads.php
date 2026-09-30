<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->validateJsonColumns();
        $this->changeColumnsToText();
        $this->encryptTable('orders', 'metadata');
        $this->encryptTable('payments', 'gateway_response');
        $this->encryptTable('payments', 'metadata');
    }

    public function down(): void
    {
        $this->decryptTable('orders', 'metadata');
        $this->decryptTable('payments', 'gateway_response');
        $this->decryptTable('payments', 'metadata');
        $this->changeColumnsToJson();
    }

    private function changeColumnsToText(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->text('metadata')->nullable()->change();
        });
        Schema::table('payments', function (Blueprint $table): void {
            $table->text('gateway_response')->nullable()->change();
            $table->text('metadata')->nullable()->change();
        });
    }

    private function changeColumnsToJson(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->json('metadata')->nullable()->change();
        });
        Schema::table('payments', function (Blueprint $table): void {
            $table->json('gateway_response')->nullable()->change();
            $table->json('metadata')->nullable()->change();
        });
    }

    private function encryptTable(string $table, string $column): void
    {
        DB::table($table)
            ->select(['id', $column])
            ->whereNotNull($column)
            ->orderBy('id')
            ->chunkById(100, function (\Illuminate\Support\Collection $rows) use ($table, $column): void {
                foreach ($rows as $row) {
                    $payload = json_decode((string) $row->{$column}, true, 512, JSON_THROW_ON_ERROR);

                    DB::table($table)
                        ->where('id', $row->id)
                        ->update([$column => Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR))]);
                }
            });
    }

    private function validateJsonColumns(): void
    {
        foreach ([
            ['orders', 'metadata'],
            ['payments', 'gateway_response'],
            ['payments', 'metadata'],
        ] as [$table, $column]) {
            DB::table($table)
                ->select(['id', $column])
                ->whereNotNull($column)
                ->orderBy('id')
                ->chunkById(100, function (\Illuminate\Support\Collection $rows) use ($column): void {
                    foreach ($rows as $row) {
                        json_decode((string) $row->{$column}, true, 512, JSON_THROW_ON_ERROR);
                    }
                });
        }
    }

    private function decryptTable(string $table, string $column): void
    {
        DB::table($table)
            ->select(['id', $column])
            ->whereNotNull($column)
            ->orderBy('id')
            ->chunkById(100, function (\Illuminate\Support\Collection $rows) use ($table, $column): void {
                foreach ($rows as $row) {
                    DB::table($table)
                        ->where('id', $row->id)
                        ->update([$column => Crypt::decryptString((string) $row->{$column})]);
                }
            });
    }
};
