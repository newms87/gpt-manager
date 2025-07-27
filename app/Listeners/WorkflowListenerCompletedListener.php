<?php

namespace App\Listeners;

use App\Events\WorkflowRunUpdatedEvent;
use App\Models\UiDemand;
use App\Models\Workflow\WorkflowListener;
use App\Services\UiDemand\UiDemandWorkflowService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class WorkflowListenerCompletedListener implements ShouldQueue
{
    use InteractsWithQueue;

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

        // Route to appropriate service based on listener type
        match ($workflowListener->listener_type) {
            UiDemand::class => app(UiDemandWorkflowService::class)->handleUiDemandWorkflowComplete($workflowRun),
            // Add other listener types here as needed
            default => null
        };
    }
}