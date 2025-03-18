<?php

namespace App\Services\Workflow;

use App\Models\Agent\Agent;
use App\Models\Prompt\AgentPromptDirective;
use App\Models\Prompt\PromptDirective;
use App\Models\ResourcePackage\ResourcePackage;
use App\Models\ResourcePackage\ResourcePackageImport;
use App\Models\ResourcePackage\ResourcePackageVersion;
use App\Models\Schema\SchemaAssociation;
use App\Models\Schema\SchemaDefinition;
use App\Models\Schema\SchemaFragment;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskDefinitionAgent;
use App\Models\Workflow\WorkflowConnection;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowNode;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Exceptions\ValidationError;

class WorkflowImportService
{
    protected ResourcePackage        $resourcePackage;
    protected ResourcePackageVersion $resourcePackageVersion;
    protected string                 $versionName;

    protected array $importedIdMap = [];

    /**
     * Resolves the resource package for the given resource package ID.
     * If one does not exist, it will be created using the name and team UUID
     */
    protected function resolveResourcePackage(string $id, string $teamUuid, string $name): ResourcePackage
    {
        return ResourcePackage::firstOrCreate(['id' => $id], [
            'name'      => $name,
            'team_uuid' => $teamUuid,
        ]);
    }

    /**
     * Resolves the resource package version for the given resource package version ID
     * If one does not exist, it will be created using the version and definitions
     */
    protected function resolveResourcePackageVersion(string $id, string $version, array $definitions): ResourcePackageVersion
    {
        return ResourcePackageVersion::firstOrCreate(['id' => $id], [
            'version'      => $version,
            'version_hash' => md5(json_encode($definitions)),
            'definitions'  => $definitions,
        ]);
    }

    /**
     * Resolves the import record for the given object type and source ID
     * NOTE: A record existing does not directly indicate an object actually exists in the system, it serves only as a
     * reference to see if an equivalent object exists (ie: the user may have deleted it) This serves to help identify
     * and use an existing object so we do not create multiple objects in case of importing multiple times
     */
    protected function resolveImport(string $objectType, string $sourceId): ResourcePackageImport
    {
        return ResourcePackageImport::firstOrCreate([
            'team_uuid'           => team()->uuid,
            'resource_package_id' => $this->resourcePackage->id,
            'object_type'         => $objectType,
            'source_object_id'    => $sourceId,
        ]);
    }

    /**
     * Import a workflow definition from the JSON data
     */
    public function importFromJson(array $workflowDefinitionJson): WorkflowDefinition
    {
        $resourcePackageId        = $workflowDefinitionJson['resource_package_id'] ?? null;
        $resourcePackageVersionId = $workflowDefinitionJson['resource_package_version_id'] ?? null;
        $teamUuid                 = $workflowDefinitionJson['team_uuid'] ?? null;
        $name                     = $workflowDefinitionJson['name'] ?? null;
        $version                  = $workflowDefinitionJson['version'] ?? null;
        $definitions              = $workflowDefinitionJson['definitions'] ?? [];

        $this->versionName = "$name - $version";

        // Resolve the resource package locally
        $this->resourcePackage        = $this->resolveResourcePackage($resourcePackageId, $teamUuid, $name);
        $this->resourcePackageVersion = $this->resolveResourcePackageVersion($resourcePackageVersionId, $version, $definitions);

        // This defines the correct order to import so the relationships are resolved correctly
        $importClasses = [
            PromptDirective::class      => ['is_team' => true],
            Agent::class                => ['is_team' => true],
            SchemaDefinition::class     => ['is_team' => true],
            SchemaFragment::class       => [],
            TaskDefinition::class       => ['is_team' => true],
            TaskDefinitionAgent::class  => [],
            SchemaAssociation::class    => [],
            AgentPromptDirective::class => [],
            WorkflowDefinition::class   => ['is_team' => true],
            WorkflowNode::class         => [],
            WorkflowConnection::class   => [],
        ];

        foreach($importClasses as $objectType => $config) {
            $this->importResource($objectType, $config, $definitions[$objectType] ?? []);
        }

        $workflowDefinitions  = $this->importedIdMap[WorkflowDefinition::class] ?? [];
        $workflowDefinitionId = reset($workflowDefinitions);
        $workflowDefinition   = WorkflowDefinition::find($workflowDefinitionId);

        if (!$workflowDefinition) {
            throw new ValidationError('Failed to import workflow definition: The object was not created');
        }

        return $workflowDefinition;
    }


    /**
     * Import a class from the JSON data
     * @param Model|string $objectType
     **/
    protected function importResource(string $objectType, array $config, array $definitions): void
    {
        foreach($definitions as $sourceId => $definition) {
            $isTeam = !empty($config['is_team']);

            $resourcePackageImport = $this->resolveImport($objectType, $sourceId);
            $localObject           = $resourcePackageImport->getLocalObject();

            if (!$localObject) {
                $localObject = (new $objectType);

                if ($isTeam) {
                    $localObject->team_id = team()->id;
                }

                $localObject->resource_package_import_id = $resourcePackageImport->id;
            }

            foreach($definition as $key => $value) {

                // First check if the value is a reference to another object
                // (ie: The value is in the form "App\\Models\\Workflow\\WorkflowDefinition::3")
                if ($value && is_string($value)) {
                    $parts = explode(':', $value);
                    if (count($parts) === 2) {
                        $relationObjectType = $parts[0];
                        $relationSourceId   = $parts[1];
                        $mappedId           = $this->importedIdMap[$relationObjectType][$relationSourceId] ?? null;

                        // resolve the mapped ID instead of using the original value
                        if ($mappedId) {
                            $value = $mappedId;
                        }
                    }
                }

                if ($key === 'name') {
                    $value .= ' (' . $this->versionName . ')';
                }
                $localObject->$key = $value;
            }

            $localObject->save();
            $this->importedIdMap[$objectType][$sourceId] = $localObject->id;

            // Update the resource package import record with the local object ID so we can track future imports / updates
            $resourcePackageImport->local_object_id             = $localObject->id;
            $resourcePackageImport->resource_package_version_id = $this->resourcePackageVersion->id;
            $resourcePackageImport->save();
        }
    }
}
