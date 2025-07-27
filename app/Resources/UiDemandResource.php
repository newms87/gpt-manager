<?php

namespace App\Resources;

use App\Models\UiDemand;
use App\Resources\Auth\UserResource;
use App\Resources\TeamObjectResource;
use App\Resources\Workflow\WorkflowListenerResource;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;
use Newms87\Danx\Resources\StoredFileResource;

class UiDemandResource extends ActionResource
{
    public static function data(UiDemand $demand): array
    {
        return [
            'id' => $demand->id,
            'title' => $demand->title,
            'description' => $demand->description,
            'status' => $demand->status,
            'metadata' => $demand->metadata,
            'team_object_id' => $demand->team_object_id,
            'submitted_at' => $demand->submitted_at,
            'completed_at' => $demand->completed_at,
            'created_at' => $demand->created_at,
            'updated_at' => $demand->updated_at,
            'can_be_submitted' => $demand->canBeSubmitted(),
            
            // Workflow state helpers
            'can_extract_data' => $demand->canExtractData(),
            'can_write_demand' => $demand->canWriteDemand(),
            'is_extract_data_running' => $demand->isExtractDataRunning(),
            'is_write_demand_running' => $demand->isWriteDemandRunning(),
            
            // Relationships
            'user' => fn($fields) => UserResource::make($demand->user, $fields),
            'files' => fn($fields) => StoredFileResource::collection($demand->storedFiles, $fields),
            'files_count' => fn($fields) => $demand->storedFiles_count ?? $demand->storedFiles()->count(),
            'team_object' => fn($fields) => $demand->team_object_id ? TeamObjectResource::make($demand->teamObject, $fields) : null,
            'workflow_listeners' => fn($fields) => WorkflowListenerResource::collection($demand->workflowListeners, $fields),
        ];
    }

    public static function details(Model $model, ?array $includeFields = null): array
    {
        return static::make($model, $includeFields ?? [
            'user' => true,
            'files' => true,
            'team_object' => true,
            'workflow_listeners' => true,
        ]);
    }

}