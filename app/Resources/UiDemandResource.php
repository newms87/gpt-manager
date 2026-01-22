<?php

namespace App\Resources;

use App\Models\Demand\UiDemand;
use App\Resources\Auth\UserResource;
use App\Resources\TeamObject\TeamObjectResource;
use App\Resources\Usage\UsageSummaryResource;
use App\Resources\Workflow\ArtifactResource;
use App\Resources\Workflow\WorkflowRunResource;
use App\Services\UiDemand\UiDemandWorkflowConfigService;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;
use Newms87\Danx\Resources\StoredFileResource;

class UiDemandResource extends ActionResource
{
    public static function data(UiDemand $demand): array
    {
        return [
            'title'                   => $demand->title,
            'description'             => $demand->description,
            'status'                  => $demand->status,
            'metadata'                => $demand->metadata,
            'team_object_id'          => $demand->team_object_id,
            'completed_at'            => $demand->completed_at,
            'created_at'              => $demand->created_at,
            'updated_at'              => $demand->updated_at,

            // Dynamic workflow system
            'workflow_runs'           => fn($fields) => static::formatWorkflowRuns($demand, $fields),
            'workflow_config'         => fn() => app(UiDemandWorkflowConfigService::class)->getWorkflowsForApi(),
            'artifact_sections'       => fn($fields) => static::formatArtifactSections($demand, $fields),

            // Relationships
            'user'                    => fn($fields) => UserResource::make($demand->user, $fields),
            'input_files'             => fn($fields) => StoredFileResource::collection($demand->inputFiles, $fields),
            'output_files'            => fn($fields) => StoredFileResource::collection($demand->outputFiles, $fields),
            'input_files_count'       => $demand->input_files_count,
            'output_files_count'      => $demand->output_files_count,
            'team_object'             => fn($fields) => TeamObjectResource::make($demand->teamObject, $fields),

            // Usage tracking
            'usage_summary'           => UsageSummaryResource::make($demand->usageSummary),
        ];
    }

    /**
     * Format workflow runs keyed by workflow key
     * Returns an array of all runs per key, sorted by created_at desc
     */
    protected static function formatWorkflowRuns(UiDemand $demand, mixed $fields): array
    {
        $configService = app(UiDemandWorkflowConfigService::class);
        $workflows     = $configService->getWorkflows();
        $workflowRuns  = [];

        foreach ($workflows as $workflow) {
            $key                = $workflow['key'];
            $runs               = $demand->getWorkflowRunsForKey($key);
            // Return array of WorkflowRun resources
            $workflowRuns[$key] = WorkflowRunResource::collection($runs, $fields);
        }

        return $workflowRuns;
    }

    /**
     * Format artifact sections for display based on workflow config
     */
    protected static function formatArtifactSections(UiDemand $demand, mixed $fields): array
    {
        $configService    = app(UiDemandWorkflowConfigService::class);
        $workflows        = $configService->getWorkflows();
        $artifactSections = [];

        foreach ($workflows as $workflow) {
            $displayConfig = $workflow['display_artifacts'] ?? false;

            // Skip workflows that don't display artifacts
            if (!$displayConfig) {
                continue;
            }

            $category  = $displayConfig['artifact_category'] ?? null;
            $artifacts = ($category && $demand->teamObject) ? $demand->teamObject->getArtifactsByCategory($category) : collect();

            $artifactSections[] = [
                'workflow_key'      => $workflow['key'],
                'section_title'     => $displayConfig['section_title']  ?? $workflow['label'],
                'artifact_category' => $category,
                'display_type'      => $displayConfig['display_type']   ?? 'artifacts',
                'editable'          => $displayConfig['editable']       ?? false,
                'deletable'         => $displayConfig['deletable']      ?? false,
                'color'             => $workflow['color']               ?? 'blue',
                'artifacts'         => ArtifactResource::collection($artifacts, $fields['artifacts'] ?? []),
            ];
        }

        return $artifactSections;
    }

    public static function details(Model $model, ?array $includeFields = null): array
    {
        return static::make($model, $includeFields ?? [
            'user'              => true,
            'input_files'       => ['thumb' => true],
            'output_files'      => ['thumb' => true],
            'team_object'       => true,
            'workflow_runs'     => true,
            'workflow_config'   => true,
            'artifact_sections' => true,
        ]);
    }
}
