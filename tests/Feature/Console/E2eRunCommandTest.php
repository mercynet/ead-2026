<?php

use Illuminate\Support\Facades\Http;

/*
|--------------------------------------------------------------------------
| Contrato de exit code do runner E2E
|--------------------------------------------------------------------------
| Falha de cleanup/teardown deixa fixtures órfãs; o runner precisa refletir
| isso no exit code (senão declara falso sucesso deixando resíduo no banco).
*/

it('returns a non-zero exit code when spec cleanup fails (residue left behind)', function (): void {
    // Canário e chamadas HTTP são fingidas: o alvo do teste é a contabilidade de
    // falhas do runner, não o alinhamento real servidor↔DB.
    Http::fake(['*' => Http::response(['data' => ['ok' => true]], 200)]);

    $this->artisan('e2e:run', [
        'spec' => '_fixtures/cleanup-throws',
        '--base' => 'http://e2e.test',
    ])->assertExitCode(1);
});

it('returns zero when cleanup succeeds and no case fails', function (): void {
    Http::fake(['*' => Http::response(['data' => ['ok' => true]], 200)]);

    $this->artisan('e2e:run', [
        'spec' => '_fixtures/cleanup-ok',
        '--base' => 'http://e2e.test',
    ])->assertExitCode(0);
});
