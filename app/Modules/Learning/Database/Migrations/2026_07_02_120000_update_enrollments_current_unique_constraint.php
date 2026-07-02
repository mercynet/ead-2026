<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE enrollments
                ADD COLUMN current_enrollment_key TINYINT UNSIGNED GENERATED ALWAYS AS (
                    CASE
                        WHEN status IN ('pending', 'active') THEN 1
                        ELSE NULL
                    END
                ) STORED,
                DROP INDEX enrollments_tenant_id_user_id_course_id_unique,
                ADD UNIQUE KEY enrollments_tenant_user_course_current_unique (
                    tenant_id,
                    user_id,
                    course_id,
                    current_enrollment_key
                )
        SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE enrollments
                DROP INDEX enrollments_tenant_user_course_current_unique,
                DROP COLUMN current_enrollment_key,
                ADD UNIQUE KEY enrollments_tenant_id_user_id_course_id_unique (
                    tenant_id,
                    user_id,
                    course_id
                )
        SQL);
    }
};
