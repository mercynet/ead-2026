<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates core identity tables', function (): void {
    expect(Schema::hasTable('tenants'))->toBeTrue();
    expect(Schema::hasTable('tenant_customizations'))->toBeTrue();
    expect(Schema::hasTable('tenant_integrations'))->toBeTrue();
});

it('adds mandatory identity fields to users table', function (): void {
    expect(Schema::hasColumns('users', [
        'tenant_id',
        'cpf',
        'headline',
        'bio',
        'avatar',
        'linkedin_url',
        'twitter_url',
    ]))->toBeTrue();
});
