<?php

namespace App\Providers;

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

        $modelsWithTrait = ModelHelper::getModelsWithTrait(HasRelationCountersTrait::class);

        foreach($modelsWithTrait as $model) {
            $model::registerRelationshipCounters();
        }
    }
}
