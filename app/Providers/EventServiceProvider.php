<?php

namespace App\Providers;

use App\Events\JobDispatchUpdatedEvent;
use App\Events\WorkflowRunUpdatedEvent;
use App\Listeners\WorkflowListenerCompletedListener;
use App\Models\Workflow\WorkflowRun;
use DB;
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

        // Dispatch the jobs w/ team info
        JobDispatch::creating(function (JobDispatch $jobDispatch) {
            $jobDispatch->data = ['team_id' => team()?->id];
        });

        JobDispatch::created(function (JobDispatch $jobDispatch) {
            // Broadcast the JobDispatch created event
            JobDispatchUpdatedEvent::dispatch($jobDispatch, 'created');
        });

        JobDispatch::updated(function (JobDispatch $jobDispatch) {
            // Notify the WorkflowRuns when a job dispatch worker has changed state
            if ($jobDispatch->wasChanged('status')) {
                $dispatchable = DB::table('job_dispatchables')
                    ->where('job_dispatch_id', $jobDispatch->id)
                    ->where('model_type', WorkflowRun::class)
                    ->first();

                if ($dispatchable) {
                    $workflowRun = WorkflowRun::find($dispatchable->model_id);

                    if ($workflowRun) {
                        $workflowRun->updateActiveWorkersCount();
                    }
                }
            }

            // Broadcast the JobDispatch update event
            JobDispatchUpdatedEvent::dispatch($jobDispatch, 'updated');
        });

        $modelsWithTrait = ModelHelper::getModelsWithTrait(HasRelationCountersTrait::class);

        foreach($modelsWithTrait as $model) {
            $model::registerRelationshipCounters();
        }
    }
}
