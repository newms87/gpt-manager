<?php

namespace App\Providers;

use App\Events\JobDispatchUpdatedEvent;
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
use Newms87\Danx\Helpers\ModelHelper;
use Newms87\Danx\Models\Audit\ErrorLogEntry;
use Newms87\Danx\Models\Job\JobDispatch;
use Newms87\Danx\Traits\HasRelationCountersTrait;

class EventServiceProvider extends ServiceProvider
{
    public function register()
    {
    }

    public function boot()
    {
        // Register event listeners
        Event::listen(
            WorkflowRunUpdatedEvent::class,
            [WorkflowListenerCompletedListener::class, 'handle']
        );

        Event::listen(
            WorkflowRunUpdatedEvent::class,
            [WorkflowBuilderCompletedListener::class, 'handle']
        );

        Event::listen(
            UsageEventCreated::class,
            [UiDemandUsageSubscriber::class, 'handle']
        );

        // Dispatch the jobs w/ team info
        JobDispatch::creating(function (JobDispatch $jobDispatch) {
            $jobDispatch->data = ['team_id' => team()?->id];
        });

        JobDispatch::created(function (JobDispatch $jobDispatch) {
            // Broadcast the JobDispatch created event
            JobDispatchUpdatedEvent::dispatch($jobDispatch, 'created');
        });

        JobDispatch::updated(function (JobDispatch $jobDispatch) {
            // Check for errors on ANY status change - errors can occur at any point
            if ($jobDispatch->wasChanged('status')) {
                // Only update error counts if this JobDispatch is related to a TaskProcess
                // Check via the morphToMany relationship (uses pivot table job_dispatchables)
                $hasTaskProcess = TaskProcess::whereHas('jobDispatches', function ($query) use ($jobDispatch) {
                    $query->where('job_dispatch.id', $jobDispatch->id);
                })->exists();

                if ($hasTaskProcess) {
                    app(TaskProcessErrorTrackingService::class)
                        ->updateErrorCountsForJobDispatch($jobDispatch);
                }

                // Notify the WorkflowRuns when a job dispatch worker has changed state
                $workflowRuns = WorkflowRun::whereHas('jobDispatches', function ($query) use ($jobDispatch) {
                    $query->where('job_dispatch.id', $jobDispatch->id);
                })->get();

                foreach ($workflowRuns as $workflowRun) {
                    $workflowRun->updateActiveWorkersCount();
                }
            }

            // Broadcast the JobDispatch update event
            JobDispatchUpdatedEvent::dispatch($jobDispatch, 'updated');
        });

        // Update error counts when ErrorLogEntry is created
        // This ensures counts are updated AFTER the error is written to the database
        ErrorLogEntry::created(function (ErrorLogEntry $errorLogEntry) {
            // Only process if this entry has an audit request
            if (!$errorLogEntry->audit_request_id) {
                return;
            }

            // Find JobDispatches associated with this audit request
            $jobDispatches = JobDispatch::where('running_audit_request_id', $errorLogEntry->audit_request_id)->get();

            foreach ($jobDispatches as $jobDispatch) {
                // Check if this JobDispatch is related to a TaskProcess
                $hasTaskProcess = TaskProcess::whereHas('jobDispatches', function ($query) use ($jobDispatch) {
                    $query->where('job_dispatch.id', $jobDispatch->id);
                })->exists();

                if ($hasTaskProcess) {
                    app(TaskProcessErrorTrackingService::class)
                        ->updateErrorCountsForJobDispatch($jobDispatch);
                }
            }
        });

        $modelsWithTrait = ModelHelper::getModelsWithTrait(HasRelationCountersTrait::class);

        foreach ($modelsWithTrait as $model) {
            $model::registerRelationshipCounters();
        }
    }
}
