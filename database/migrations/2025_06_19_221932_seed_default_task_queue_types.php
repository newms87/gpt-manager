<?php

use Database\Seeders\TaskQueueTypeSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Run the TaskQueueType seeder to create default queue types
        (new TaskQueueTypeSeeder())->run();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the default task queue types
        $defaultNames = ['LLM Tasks', 'Convert API Tasks', 'General Tasks'];
        \App\Models\Task\TaskQueueType::whereIn('name', $defaultNames)->delete();
    }
};
