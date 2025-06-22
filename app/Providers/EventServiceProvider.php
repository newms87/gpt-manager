<?php

namespace App\Providers;

use App\Models\Workflow\WorkflowRun;
use DB;
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
        // Dispatch the jobs w/ team info
        JobDispatch::creating(function (JobDispatch $jobDispatch) {
            $jobDispatch->data = ['team_id' => team()?->id];
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
        });

        $modelsWithTrait = ModelHelper::getModelsWithTrait(HasRelationCountersTrait::class);

        foreach($modelsWithTrait as $model) {
            $model::registerRelationshipCounters();
        }
    }
}
