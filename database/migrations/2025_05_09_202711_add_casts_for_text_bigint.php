<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up()
    {
        // Only run for PostgreSQL
        if (DB::connection()->getDriverName() === 'pgsql') {
            // Create implicit cast from bigint to text
            DB::statement('CREATE CAST (bigint AS text) WITH INOUT AS IMPLICIT');

            // Create implicit cast from text to bigint
            DB::statement('CREATE CAST (text AS bigint) WITH INOUT AS IMPLICIT');
        }
    }

    public function down()
    {
        // Only run for PostgreSQL
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP CAST IF EXISTS (bigint AS text)');
            DB::statement('DROP CAST IF EXISTS (text AS bigint)');
        }
    }
};
