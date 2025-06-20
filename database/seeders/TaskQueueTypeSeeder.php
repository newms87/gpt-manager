<?php

namespace Database\Seeders;

use App\Models\TaskQueueType;
use Illuminate\Database\Seeder;

class TaskQueueTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $queueTypes = [
            [
                'name' => 'LLM Tasks',
                'description' => 'AI and language model tasks (GPU-intensive workloads)',
                'max_workers' => 10,
                'queue_name' => 'task-process', // All tasks run on task-process queue
                'is_active' => true,
            ],
            [
                'name' => 'Convert API Tasks',
                'description' => 'Document conversion and processing tasks (API rate limited)',
                'max_workers' => 10,
                'queue_name' => 'task-process', // All tasks run on task-process queue
                'is_active' => true,
            ],
            [
                'name' => 'General Tasks',
                'description' => 'General purpose computational tasks',
                'max_workers' => 50,
                'queue_name' => 'task-process', // All tasks run on task-process queue
                'is_active' => true,
            ],
        ];

        foreach ($queueTypes as $queueType) {
            TaskQueueType::updateOrCreate(
                ['name' => $queueType['name']], // Find by name
                $queueType // Update or create with these values
            );
        }
    }
}
