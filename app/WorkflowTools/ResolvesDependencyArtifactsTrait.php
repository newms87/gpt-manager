<?php

namespace App\WorkflowTools;

use App\Models\Workflow\WorkflowJob;
use App\Models\Workflow\WorkflowJobDependency;
use App\Models\Workflow\WorkflowJobRun;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Newms87\Danx\Helpers\ArrayHelper;

trait ResolvesDependencyArtifactsTrait
{
    /**
     * Resolve the artifacts for each dependency based on the prerequisite job run artifacts and the group by and
     * include fields of the dependency
     */
    public function resolveDependencyArtifacts(WorkflowJob $workflowJob, array|Collection $prerequisiteJobRuns = []): array
    {
        try {
            Log::debug(" $workflowJob resolving artifacts for each dependency");
            $dependencyArtifactGroups = $this->getArtifactGroupsByDependency($workflowJob->dependencies, $prerequisiteJobRuns);
        } catch(Exception $e) {
            Log::error("Failed to resolve dependency artifacts $workflowJob: " . $e->getMessage());

            return [];
        }

        return $this->generateArtifactGroupTuples($dependencyArtifactGroups);
    }

    /**
     * Get the set of artifact groups for each dependency based on the output artifacts of the completed prerequisite
     * jobs matching the dependency.
     *
     * Groupings are based on each dependencies' group_by field, and the include_fields are used to filter the data
     */
    public function getArtifactGroupsByDependency(array|Collection $dependencies, array|Collection $prerequisiteJobRuns): ?array
    {
        $groups = [];
        foreach($dependencies as $dependency) {
            $prerequisiteJob = $prerequisiteJobRuns[$dependency->depends_on_workflow_job_id] ?? null;
            if (!$prerequisiteJob) {
                throw new Exception("Missing prerequisite job for $dependency");
            }

            $artifactGroups = $this->getArtifactGroups($dependency, $prerequisiteJob);

            Log::debug("$dependency created artifact groups: " . implode(',', array_keys($artifactGroups)));
            $groups[$dependency->id] = $artifactGroups;
        }

        return $groups;
    }

    /**
     * Build the artifact groups based on the dependency's group by field and filtering data by the include fields
     */
    public function getArtifactGroups(WorkflowJobDependency $dependency, WorkflowJobRun $workflowJobRun): array
    {
        $groups = [];
        foreach($workflowJobRun->artifacts as $artifact) {
            $includedData = ArrayHelper::extractNestedData($artifact->data, $dependency->include_fields);
            if (!$dependency->group_by) {
                $groups['default'][$artifact->id] = $includedData;
                continue;
            }

            $groupByData = ArrayHelper::extractNestedData($artifact->data, $dependency->group_by);

            foreach($groupByData as $groupByValue) {
                if ($groupByValue === null) {
                    continue;
                }

                if (is_scalar($groupByValue)) {
                    $groupByKey = (string)$groupByValue;
                } else {
                    ArrayHelper::recursiveKsort($groupByValue);
                    $groupByKey   = '';
                    $requiresHash = false;
                    foreach($groupByValue as $key => $value) {
                        if (is_array($value)) {
                            $requiresHash = true;
                            continue;
                        }
                        $groupByKey .= ($groupByKey ? '|' : '') . "$key:$value";
                    }
                    if (strlen($groupByKey) > 30) {
                        $requiresHash = true;
                        $groupByKey   = substr($groupByKey, 0, 20);
                    }
                    if ($requiresHash) {
                        $groupByKey .= substr(md5(json_encode($groupByValue)), 0, 10);
                    }
                }
                $groups[$groupByKey][$artifact->id] = $includedData;
            }
        }

        return $groups;
    }

    /**
     * Performs a Cartesian product (ie cross product) on the artifact groups to generate tuples
     */
    public function generateArtifactGroupTuples(array $dependencyArtifactGroups): array
    {
        if (!$dependencyArtifactGroups) {
            return ['default' => ''];
        }

        $groupTuples = [];
        foreach($dependencyArtifactGroups as $artifactGroups) {
            if (empty($groupTuples)) {
                foreach($artifactGroups as $artifactGroupKey => $artifacts) {
                    $groupTuples[$artifactGroupKey] = $artifacts;
                }
            } else {
                $newGroupTuples = [];
                foreach($groupTuples as $tupleKey => $tupleArtifacts) {
                    foreach($artifactGroups as $artifactGroupKey => $artifacts) {
                        $newGroupTuples[$tupleKey . '|' . $artifactGroupKey] = array_merge($tupleArtifacts, $artifacts);
                    }
                }
                $groupTuples = $newGroupTuples;
            }
        }

        return $groupTuples;
    }
}
