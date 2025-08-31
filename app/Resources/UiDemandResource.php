<?php

namespace App\Resources;

use App\Models\UiDemand;
use App\Resources\Auth\UserResource;
use App\Resources\TeamObject\TeamObjectResource;
use App\Resources\Usage\UsageSummaryResource;
use App\Resources\Workflow\WorkflowRunResource;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;
use Newms87\Danx\Resources\StoredFileResource;

class UiDemandResource extends ActionResource
{
    public static function data(UiDemand $demand): array
    {
        return [
            'id'                        => $demand->id,
            'title'                     => $demand->title,
            'description'               => $demand->description,
            'status'                    => $demand->status,
            'metadata'                  => $demand->metadata,
            'team_object_id'            => $demand->team_object_id,
            'completed_at'              => $demand->completed_at,
            'created_at'                => $demand->created_at,
            'updated_at'                => $demand->updated_at,

            // Workflow state helpers
            'can_extract_data'          => $demand->canExtractData(),
            'can_write_demand'          => $demand->canWriteDemand(),
            'is_extract_data_running'   => $demand->isExtractDataRunning(),
            'is_write_demand_running'   => $demand->isWriteDemandRunning(),

            // Workflow run relationships with progress data
            'extract_data_workflow_run' => fn($fields) => WorkflowRunResource::make($demand->getLatestExtractDataWorkflowRun(), $fields),
            'write_demand_workflow_run' => fn($fields) => WorkflowRunResource::make($demand->getLatestWriteDemandWorkflowRun(), $fields),

            // Relationships
            'user'                      => fn($fields) => UserResource::make($demand->user, $fields),
            'input_files'               => fn($fields) => StoredFileResource::collection($demand->inputFiles, $fields),
            'output_files'              => fn($fields) => StoredFileResource::collection($demand->outputFiles, $fields),
            'input_files_count'         => $demand->inputFiles()->count(),
            'output_files_count'        => $demand->outputFiles()->count(),
            'team_object'               => fn($fields) => TeamObjectResource::make($demand->teamObject, $fields),

            // Usage tracking
            'usage_summary'             => UsageSummaryResource::make($demand->usageSummary),
        ];
    }

    public static function details(Model $model, ?array $includeFields = null): array
    {
        return static::make($model, $includeFields ?? [
            'user'                      => true,
            'input_files'               => ['thumb' => true],
            'output_files'              => ['thumb' => true],
            'team_object'               => true,
            'extract_data_workflow_run' => true,
            'write_demand_workflow_run' => true,
        ]);
    }

}
