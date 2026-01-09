<?php

namespace App\Providers;

use App\Events\UsageEventCreated;
use App\Events\WorkflowRunUpdatedEvent;
use App\Listeners\UiDemandUsageSubscriber;
use App\Listeners\WorkflowBuilder\WorkflowBuilderCompletedListener;
use App\Listeners\WorkflowListenerCompletedListener;
use App\Models\Task\TaskProcess;
use App\Models\Workflow\WorkflowRun;
use App\Services\Task\TaskProcessErrorTrackingService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Newms87\Danx\Events\ApiLogUpdatedEvent;
use Newms87\Danx\Events\JobDispatchUpdatedEvent;
use Newms87\Danx\Helpers\ModelHelper;
use Newms87\Danx\Models\Audit\ApiLog;
use Newms87\Danx\Models\Audit\ErrorLogEntry;
use Newms87\Danx\Models\Job\JobDispatch;
use Newms87\Danx\Traits\HasRelationCountersTrait;

class EventServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        $this->registerWorkflowEventListeners();
        $this->registerUsageEventListeners();
        $this->registerJobDispatchEvents();
        $this->registerApiLogEvents();
        $this->registerErrorLogEvents();
        $this->registerRelationCounters();
    }

    private function registerWorkflowEventListeners(): void
    {
        Event::listen(
            WorkflowRunUpdatedEvent::class,
            [WorkflowListenerCompletedListener::class, 'handle']
        );

        Event::listen(
            WorkflowRunUpdatedEvent::class,
            [WorkflowBuilderCompletedListener::class, 'handle']
        );
    }

    private function registerUsageEventListeners(): void
    {
        Event::listen(
            UsageEventCreated::class,
            [UiDemandUsageSubscriber::class, 'handle']
        );
    }

    private function registerJobDispatchEvents(): void
    {
        JobDispatch::creating(function (JobDispatch $jobDispatch) {
            $jobDispatch->team_id = team()?->id;
        });

        JobDispatch::created(function (JobDispatch $jobDispatch) {
            JobDispatchUpdatedEvent::dispatch($jobDispatch, 'created');
        });

        JobDispatch::updated(function (JobDispatch $jobDispatch) {
            // Check for errors on ANY status change - errors can occur at any point
            if ($jobDispatch->wasChanged('status')) {
                $this->handleJobDispatchStatusChange($jobDispatch);
            }

            JobDispatchUpdatedEvent::dispatch($jobDispatch, 'updated');
        });
    }

    private function handleJobDispatchStatusChange(JobDispatch $jobDispatch): void
    {
        // Only update error counts if this JobDispatch is related to a TaskProcess
        // Check via the morphToMany relationship (uses pivot table job_dispatchables)
        $hasTaskProcess = TaskProcess::whereHas('jobDispatches', fn($query) => $query->where('job_dispatch.id', $jobDispatch->id))->exists();

        if ($hasTaskProcess) {
            app(TaskProcessErrorTrackingService::class)->updateErrorCountsForJobDispatch($jobDispatch);
        }

        // Notify the WorkflowRuns when a job dispatch worker has changed state
        $workflowRuns = WorkflowRun::whereHas('jobDispatches', fn($query) => $query->where('job_dispatch.id', $jobDispatch->id))->get();

        foreach ($workflowRuns as $workflowRun) {
            $workflowRun->updateActiveWorkersCount();
        }
    }

    private function registerApiLogEvents(): void
    {
        ApiLog::created(function (ApiLog $apiLog) {
            ApiLogUpdatedEvent::dispatch($apiLog, 'created');
        });

        ApiLog::updated(function (ApiLog $apiLog) {
            if ($apiLog->wasChanged(['status_code', 'response', 'finished_at', 'run_time_ms'])) {
                ApiLogUpdatedEvent::dispatch($apiLog, 'updated');
            }
        });
    }

    private function registerErrorLogEvents(): void
    {
        // Update error counts when ErrorLogEntry is created
        // This ensures counts are updated AFTER the error is written to the database
        ErrorLogEntry::created(function (ErrorLogEntry $errorLogEntry) {
            if (!$errorLogEntry->audit_request_id) {
                return;
            }

            $this->updateErrorCountsForAuditRequest($errorLogEntry->audit_request_id);
        });
    }

    private function updateErrorCountsForAuditRequest(int $auditRequestId): void
    {
        $jobDispatches = JobDispatch::where('running_audit_request_id', $auditRequestId)->get();

        foreach ($jobDispatches as $jobDispatch) {
            $hasTaskProcess = TaskProcess::whereHas('jobDispatches', fn($query) => $query->where('job_dispatch.id', $jobDispatch->id))->exists();

            if ($hasTaskProcess) {
                app(TaskProcessErrorTrackingService::class)->updateErrorCountsForJobDispatch($jobDispatch);
            }
        }
    }

    private function registerRelationCounters(): void
    {
        $modelsWithTrait = ModelHelper::getModelsWithTrait(HasRelationCountersTrait::class);

        foreach ($modelsWithTrait as $model) {
            $model::registerRelationshipCounters();
        }
    }
}
