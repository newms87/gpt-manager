<?php

namespace App\Services\Workflow;

use App\Models\ResourcePackage\ResourcePackageableContract;

interface WorkflowExportServiceInterface
{
    /**
     * Register a model's exported data into definitions
     */
    public function register(ResourcePackageableContract $model, array $data): int;

    /**
     * Register a related model and return reference string (e.g., "App\Models\Task\TaskDefinition:123")
     */
    public function registerRelatedModel(?ResourcePackageableContract $model = null): ?string;

    /**
     * Register multiple related models
     *
     * @param  ResourcePackageableContract[]  $models
     */
    public function registerRelatedModels($models): void;
}
