<?php

namespace App\Services\Workflow;

use App\Models\CanExportToJsonContract;
use App\Models\ResourcePackage\ResourcePackage;
use App\Models\ResourcePackage\ResourcePackageVersion;
use App\Models\Workflow\WorkflowDefinition;

class WorkflowExportService
{
    protected array $definitions = [];

    /**
     * Resolves the resource package for the given workflow definition
     */
    protected function resolveResourcePackage(WorkflowDefinition $workflowDefinition): ResourcePackage
    {
        return ResourcePackage::firstOrCreate([
            'team_uuid'     => team()->uuid,
            'resource_type' => WorkflowDefinition::class,
            'resource_id'   => $workflowDefinition->id,
        ], [
            'name' => $workflowDefinition->name,
        ]);
    }

    /**
     * Resolves the resource package version for the resolved definitions
     */
    protected function resolveResourcePackageVersion(ResourcePackage $resourcePackage): ResourcePackageVersion
    {
        $versionHash = md5(json_encode($this->definitions));

        $resourcePackageVersion = $resourcePackage->resourcePackageVersions()->firstOrNew([
            'version_hash' => $versionHash,
        ]);

        if ($resourcePackageVersion) {
            return $resourcePackageVersion;
        }

        $resourcePackageVersion->version     = '0.0.1';
        $resourcePackageVersion->definitions = $this->definitions;
        $resourcePackageVersion->save();

        return $resourcePackageVersion;
    }

    public function exportToJson(WorkflowDefinition $workflowDefinition): array
    {
        // This will load the definitions into the $this->definitions array
        $workflowDefinition->exportToJson($this);

        $resourcePackage        = $this->resolveResourcePackage($workflowDefinition);
        $resourcePackageVersion = $this->resolveResourcePackageVersion($resourcePackage);

        return [
            'resource_package_id'         => $resourcePackage->id,
            'resource_package_version_id' => $resourcePackageVersion->id,
            'team_uuid'                   => team()->uuid,
            'name'                        => $resourcePackage->name,
            'version'                     => $resourcePackageVersion->version,
            'definitions'                 => $this->definitions,
        ];
    }

    public function register(CanExportToJsonContract $model, array $data): int
    {
        $this->definitions[$model::class][$model->id] = $data;

        return $model->id;
    }

    public function registerRelatedModel(CanExportToJsonContract $model = null): string|null
    {
        if (!$model) {
            return null;
        }

        if (empty($this->definitions[$model::class][$model->id])) {
            $this->definitions[$model::class][$model->id] = true;
            $model->exportToJson($this);
        }

        return $model::class . ':' . $model->id;
    }

    /**
     * Register related models so they are exported.
     * NOTE: These models should associate themselves in their exportToJson() method to the model that has called this
     * method.
     *
     * @param CanExportToJsonContract[] $models
     */
    public function registerRelatedModels($models): void
    {
        foreach($models as $model) {
            if (empty($this->definitions[$model::class][$model->id])) {
                $model->exportToJson($this);
            }
        }
    }
}
