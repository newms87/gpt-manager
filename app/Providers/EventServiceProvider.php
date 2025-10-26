<?php

namespace App\Providers;

use App\Events\JobDispatchUpdatedEvent;
use App\Events\UsageEventCreated;
use App\Events\WorkflowRunUpdatedEvent;
use App\Listeners\UiDemandUsageSubscriber;
use App\Listeners\WorkflowBuilder\WorkflowBuilderCompletedListener;
use App\Listeners\WorkflowListenerCompletedListener;
use App\Models\Workflow\WorkflowRun;
use App\Services\Task\TaskProcessErrorTrackingService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Newms87\Danx\Helpers\ModelHelper;
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
                // Update error counts for any associated TaskProcesses
                app(TaskProcessErrorTrackingService::class)
                    ->updateErrorCountsForJobDispatch($jobDispatch);

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

        $modelsWithTrait = ModelHelper::getModelsWithTrait(HasRelationCountersTrait::class);

        foreach ($modelsWithTrait as $model) {
            $model::registerRelationshipCounters();
        }
    }
}
