<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')
                ->nullable()
                ->after('id')
                ->constrained('tenants')
                ->nullOnDelete();
            $table->string('cpf', 14)->nullable()->unique()->after('email');
            $table->string('headline')->nullable()->after('cpf');
            $table->text('bio')->nullable()->after('headline');
            $table->string('avatar')->nullable()->after('bio');
            $table->string('linkedin_url')->nullable()->after('avatar');
            $table->string('twitter_url')->nullable()->after('linkedin_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropUnique(['cpf']);
            $table->dropColumn([
                'tenant_id',
                'cpf',
                'headline',
                'bio',
                'avatar',
                'linkedin_url',
                'twitter_url',
            ]);
        });
    }
};
