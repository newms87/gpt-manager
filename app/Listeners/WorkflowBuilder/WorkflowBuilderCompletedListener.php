<?php

namespace App\Listeners\WorkflowBuilder;

use App\Events\WorkflowRunUpdatedEvent;
use App\Models\Workflow\WorkflowBuilderChat;
use App\Services\WorkflowBuilder\WorkflowBuilderService;
use App\Traits\HasDebugLogging;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class WorkflowBuilderCompletedListener implements ShouldQueue
{
    use InteractsWithQueue, HasDebugLogging;

    public function handle(WorkflowRunUpdatedEvent $event): void
    {
        $workflowRun = $event->getWorkflowRun();

        if (!$workflowRun->isFinished()) {
            return;
        }

        // Check if this is a workflow builder workflow run
        $workflowBuilderChat = $this->findWorkflowBuilderChat($workflowRun);

        static::log('triggered', [
            'workflow_run_id' => $workflowRun->id,
            'workflow_name' => $workflowRun->workflowDefinition->name,
            'builder_chat_id' => $workflowBuilderChat?->id,
            'is_builder_workflow' => (bool) $workflowBuilderChat,
        ]);

        if (!$workflowBuilderChat) {
            return;
        }

        // Verify this is the expected workflow run for the chat
        if ($workflowBuilderChat->current_workflow_run_id !== $workflowRun->id) {
            static::log('workflow_run_mismatch', [
                'workflow_run_id' => $workflowRun->id,
                'expected_run_id' => $workflowBuilderChat->current_workflow_run_id,
                'chat_id' => $workflowBuilderChat->id,
            ]);
            return;
        }

        try {
            // Call WorkflowBuilderService to process the completion
            app(WorkflowBuilderService::class)->processWorkflowCompletion($workflowBuilderChat, $workflowRun);

            static::log('processed_completion', [
                'workflow_run_id' => $workflowRun->id,
                'chat_id' => $workflowBuilderChat->id,
                'new_status' => $workflowBuilderChat->fresh()->status,
            ]);
        } catch (Exception $e) {
            static::log('processing_failed', [
                'workflow_run_id' => $workflowRun->id,
                'chat_id' => $workflowBuilderChat->id,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
            ]);

            // Update chat status to failed if processing fails
            $workflowBuilderChat->updatePhase(WorkflowBuilderChat::STATUS_FAILED, [
                'processing_error' => $e->getMessage(),
                'processing_failed_at' => now()->toISOString(),
            ]);

            // Re-throw to ensure the queue job fails and can be retried
            throw $e;
        }
    }

    /**
     * Find WorkflowBuilderChat associated with this workflow run
     */
    protected function findWorkflowBuilderChat($workflowRun): ?WorkflowBuilderChat
    {
        // First check if this is the "LLM Workflow Builder" workflow
        if ($workflowRun->workflowDefinition->name !== 'LLM Workflow Builder') {
            return null;
        }

        // Since the LLM Workflow Builder is system-owned (team_id = null) but can be used by any team,
        // we find the WorkflowBuilderChat by the workflow run ID without team restriction
        return WorkflowBuilderChat::where('current_workflow_run_id', $workflowRun->id)
            ->where('status', WorkflowBuilderChat::STATUS_BUILDING_WORKFLOW)
            ->first();
    }
}