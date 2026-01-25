<?php

namespace App\Listeners;

use App\Events\WorkflowRunUpdatedEvent;
use App\Models\Demand\UiDemand;
use App\Models\Workflow\WorkflowListener;
use App\Services\UiDemand\UiDemandWorkflowService;
use Newms87\Danx\Traits\HasDebugLogging;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Newms87\Danx\Helpers\LockHelper;

class WorkflowListenerCompletedListener implements ShouldQueue
{
    use HasDebugLogging, InteractsWithQueue;

    public function handle(WorkflowRunUpdatedEvent $event): void
    {
        $workflowRun = $event->getWorkflowRun();

        if (!$workflowRun->isFinished()) {
            return;
        }

        $workflowListener = WorkflowListener::findForWorkflowRun($workflowRun);

        if (!$workflowListener) {
            return;
        }
        
        static::logDebug('triggered', [
            'workflow_run_id' => $workflowRun->id,
            'listener_type'   => $workflowListener?->listener_type,
        ]);

        // Check if already completed - prevents re-processing
        if ($workflowListener->completed_at) {
            static::logDebug('already_completed', [
                'workflow_run_id' => $workflowRun->id,
                'listener_id'     => $workflowListener->id,
                'completed_at'    => $workflowListener->completed_at->toISOString(),
            ]);

            return;
        }

        // Acquire lock to prevent race conditions
        LockHelper::acquire($workflowListener);

        try {
            // Refresh and double-check after acquiring lock
            $workflowListener->refresh();

            if ($workflowListener->completed_at) {
                static::logDebug('completed_by_another_job', [
                    'workflow_run_id' => $workflowRun->id,
                    'listener_id'     => $workflowListener->id,
                ]);

                return;
            }

            // Mark as started
            if (!$workflowListener->started_at) {
                $workflowListener->update([
                    'started_at' => now(),
                    'status'     => 'processing',
                ]);
            }

            try {
                // Route to appropriate service based on listener type
                match ($workflowListener->listener_type) {
                    UiDemand::class => app(UiDemandWorkflowService::class)->handleUiDemandWorkflowComplete($workflowRun),
                    // Add other listener types here as needed
                    default => null
                };

                // Mark as completed
                $workflowListener->update([
                    'completed_at' => now(),
                    'status'       => 'completed',
                ]);

                static::logDebug('processing_completed', [
                    'workflow_run_id' => $workflowRun->id,
                    'listener_id'     => $workflowListener->id,
                ]);

            } catch (\Exception $e) {
                // Mark as failed
                $workflowListener->update([
                    'failed_at' => now(),
                    'status'    => 'failed',
                    'metadata'  => array_merge($workflowListener->metadata ?? [], [
                        'error'       => $e->getMessage(),
                        'error_class' => get_class($e),
                    ]),
                ]);

                static::logDebug('processing_failed', [
                    'workflow_run_id' => $workflowRun->id,
                    'listener_id'     => $workflowListener->id,
                    'error'           => $e->getMessage(),
                ]);

                // Re-throw to allow queue retry mechanism
                throw $e;
            }
        } finally {
            LockHelper::release($workflowListener);
        }
    }
}
