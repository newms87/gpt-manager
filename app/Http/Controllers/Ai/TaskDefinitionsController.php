<?php

namespace App\Http\Controllers\Ai;

use App\Jobs\ClaudeCodeGenerationStreamJob;
use App\Models\Task\TaskDefinition;
use App\Repositories\TaskDefinitionRepository;
use App\Resources\TaskDefinition\TaskDefinitionResource;
use Illuminate\Http\Request;
use Newms87\Danx\Http\Controllers\ActionController;

class TaskDefinitionsController extends ActionController
{
    public static ?string $repo     = TaskDefinitionRepository::class;
    public static ?string $resource = TaskDefinitionResource::class;

    /**
     * Generate Claude code in real-time for a task definition using WebSockets
     */
    public function generateClaudeCode(Request $request, TaskDefinition $taskDefinition)
    {
        $taskDescription = $request->input('task_description');

        if (!$taskDescription) {
            return response()->json(['error' => 'Task description is required'], 400);
        }

        // Dispatch the job to generate code in the background with WebSocket streaming
        ClaudeCodeGenerationStreamJob::dispatch($taskDefinition, $taskDescription);

        return response()->json([
            'message'            => 'Code generation started',
            'task_definition_id' => $taskDefinition->id,
        ]);
    }

}
