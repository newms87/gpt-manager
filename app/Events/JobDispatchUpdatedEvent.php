<?php

namespace App\Events;

use App\Resources\Audit\JobDispatchResource;
use App\Traits\HasDebugLogging;
use Illuminate\Support\Facades\DB;
use Newms87\Danx\Events\ModelSavedEvent;
use Newms87\Danx\Models\Job\JobDispatch;

class JobDispatchUpdatedEvent extends ModelSavedEvent
{
    use HasDebugLogging;

    public function __construct(protected JobDispatch $jobDispatch, protected string $event)
    {
        // Team ID resolved in getTeamId() due to complex dispatchable relationship
        parent::__construct(
            $jobDispatch,
            $event,
            JobDispatchResource::class
        );
    }

    protected function getTeamId(): ?int
    {
        return $this->getTeamIdFromDispatchable();
    }

    protected function createdData(): array
    {
        return JobDispatchResource::make($this->jobDispatch, [
            '*'            => false,
            'name'         => true,
            'ref'          => true,
            'job_batch_id' => true,
            'status'       => true,
            'timeout_at'   => true,
            'created_at'   => true,
        ]);
    }

    protected function updatedData(): array
    {
        return JobDispatchResource::make($this->jobDispatch, [
            '*'                         => false,
            'status'                    => true,
            'running_audit_request_id'  => true,
            'dispatch_audit_request_id' => true,
            'ran_at'                    => true,
            'completed_at'              => true,
            'run_time_ms'               => true,
            'count'                     => true,
        ]);
    }

    /**
     * Get team ID from the dispatchable relationship
     */
    protected function getTeamIdFromDispatchable(): ?int
    {
        // Try to get team ID from the dispatchable model
        $dispatchable = DB::table('job_dispatchables')
            ->where('job_dispatch_id', $this->jobDispatch->id)
            ->first();

        if (!$dispatchable) {
            return null;
        }

        // Get the model class and ID
        $modelClass = $dispatchable->model_type;
        $modelId    = $dispatchable->model_id;

        // Try to get team_id from the model
        try {
            $model = $modelClass::find($modelId);
            if (!$model) {
                return null;
            }

            if (isset($model->team_id)) {
                return $model->team_id;
            }

            // For WorkflowRun, get team_id through workflowDefinition
            if (method_exists($model, 'workflowDefinition')) {
                return $model->workflowDefinition?->team_id;
            }

            // For TaskRun, get team_id through taskDefinition
            if (method_exists($model, 'taskDefinition')) {
                return $model->taskDefinition?->team_id;
            }
        } catch (\Exception $e) {
            static::log("Could not determine team_id for JobDispatch {$this->jobDispatch->id}: " . $e->getMessage());
        }

        return null;
    }
}
