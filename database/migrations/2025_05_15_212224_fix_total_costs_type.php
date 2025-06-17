<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE agent_thread_runs ALTER COLUMN total_cost TYPE numeric(10,4) USING total_cost::numeric(10,4)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
