<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update any existing task processes with 'Dispatched' status to 'Pending'
        DB::table('task_processes')
            ->where('status', 'Dispatched')
            ->update(['status' => 'Pending']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is not reversible as we've removed the concept of 'Dispatched' status
        // from the codebase. Rolling back would require restoring the old dispatching logic.
    }
};
