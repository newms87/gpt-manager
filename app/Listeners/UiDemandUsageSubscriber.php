<?php

namespace App\Listeners;

use App\Events\UsageEventCreated;
use App\Models\Agent\AgentThreadRun;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\UiDemand;
use App\Models\Workflow\WorkflowRun;
use Illuminate\Support\Collection;

class UiDemandUsageSubscriber
{
    public function handle(UsageEventCreated $event): void
    {
        $usageEvent = $event->usageEvent;

        /** @var UiDemand[] $relatedUiDemands */
        $relatedUiDemands = collect();

        if ($usageEvent->object_type === TaskProcess::class) {
            $taskProcess = $usageEvent->object;
            if ($taskProcess && $taskProcess->taskRun) {
                $relatedUiDemands = $this->getUiDemandsFromTaskRun($taskProcess->taskRun);
            }
        } elseif ($usageEvent->object_type === TaskRun::class) {
            $taskRun = $usageEvent->object;
            if ($taskRun) {
                $relatedUiDemands = $this->getUiDemandsFromTaskRun($taskRun);
            }
        } elseif ($usageEvent->object_type === WorkflowRun::class) {
            $workflowRun = $usageEvent->object;
            if ($workflowRun) {
                $relatedUiDemands = $this->getUiDemandsFromWorkflowRun($workflowRun);
            }
        } elseif ($usageEvent->object_type === AgentThreadRun::class) {
            /** @var AgentThreadRun $agentThreadRun */
            $agentThreadRun = $usageEvent->object;
            if ($agentThreadRun) {
                $taskProcess = $agentThreadRun->agentThread?->taskProcesses()->first();
                if ($taskProcess && $taskProcess->taskRun) {
                    $relatedUiDemands = $this->getUiDemandsFromTaskRun($taskProcess->taskRun);
                }
            }
        }

        foreach($relatedUiDemands as $uiDemand) {
            $uiDemand->subscribeToUsageEvent($usageEvent);
            $uiDemand->refreshUsageSummaryFromSubscribedEvents();
        }
    }

    private function getUiDemandsFromTaskRun(TaskRun $taskRun): Collection
    {
        if (!$taskRun->workflowRun) {
            return collect();
        }

        return $this->getUiDemandsFromWorkflowRun($taskRun->workflowRun);
    }

    private function getUiDemandsFromWorkflowRun(WorkflowRun $workflowRun): Collection
    {
        return UiDemand::whereHas('workflowRuns', function ($query) use ($workflowRun) {
            $query->where('workflow_run_id', $workflowRun->id);
        })->get();
    }
}
