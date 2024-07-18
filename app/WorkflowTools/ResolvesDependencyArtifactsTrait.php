<?php

namespace App\WorkflowTools;

use App\Models\Workflow\WorkflowJob;
use App\Models\Workflow\WorkflowJobDependency;
use App\Models\Workflow\WorkflowJobRun;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
            Log::debug("$workflowJob resolving artifacts for each dependency");
            $dependencyArtifactGroups = $this->getArtifactGroupsByDependency($workflowJob->dependencies, $prerequisiteJobRuns);

            return $this->generateArtifactGroupTuples($dependencyArtifactGroups);
        } catch(Exception $e) {
            Log::error("Failed to resolve dependency artifacts $workflowJob: " . $e->getMessage());

            return [];
        }
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
                $groups['default'][] = $includedData;
                continue;
            }

            $groupByData = ArrayHelper::extractNestedData($artifact->data, $dependency->group_by);

            foreach($groupByData as $groupByIndex => $groupByValue) {
                if ($groupByValue === null) {
                    continue;
                }

                $groupByKey = $this->generateGroupByKey($groupByValue);

                // If groupByKey is set, add the included data to the group
                if ($groupByKey) {
                    $groups[$groupByKey][] = $includedData;
                } else {
                    // If group by key is still empty, that means this entry is an array of arrays. Groups should be made for each element in the array
                    foreach($groupByValue as $group) {
                        $groupByKey = $this->generateGroupByKey($group);
                        if ($groupByKey) {
                            $resolvedData = $includedData;

                            // For the group index field, remove it in favor a singular version of the field
                            // NOTE: $group is one of the elements in the array of arrays (ie: the singular version of the groupByIndex)
                            unset($resolvedData[$groupByIndex]);
                            $resolvedData[Str::singular($groupByIndex)] = $group;

                            $groups[$groupByKey][] = $resolvedData;
                        } else {
                            throw new Exception("Failed to generate group key: Value too complex: " . json_encode($groupByValue));
                        }
                    }
                }
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

    /**
     * Generates a unique key for grouping data based on the groupByValue.
     * If the groupByValue is an array, it will be sorted and concatenated into a string.
     * If the key is too long, a hash will be added to make the key unique.
     * If the groupByValue is an array of arrays, returns false, as keys should be generated only for the children.
     */
    public function generateGroupByKey($groupByValue, $maxKeyLength = 30, $hashLength = 10)
    {
        // A flag to indicate there is data not defined in the key,
        // so we need to add a hash to make the key unique to the data
        $requiresHash = false;

        if (is_scalar($groupByValue)) {
            $groupByKey = (string)$groupByValue;
        } else {
            ArrayHelper::recursiveKsort($groupByValue);
            $groupByKey = '';
            foreach($groupByValue as $key => $value) {
                if (is_array($value)) {
                    // Can't make a readable unique key from an array value, so just make a hash of all the data
                    $requiresHash = true;
                    continue;
                }
                $thisKey = ($groupByKey ? '|' : '') . "$key:$value";

                // If we've exceeded the key length, we need to add a hash to make the key unique
                if ($groupByKey && strlen($groupByKey . $thisKey) > $maxKeyLength) {
                    $requiresHash = true;
                    break;
                }

                $groupByKey .= $thisKey;
            }
        }

        // If groupByKey is set, we need to check if it's too long and add a hash to make it unique
        if (!$groupByKey) {
            return false;
        }

        if (strlen($groupByKey) > $maxKeyLength) {
            $requiresHash = true;
            $groupByKey   = substr($groupByKey, 0, $maxKeyLength - $hashLength);
        }
        if ($requiresHash) {
            $groupByKey .= substr(md5(json_encode($groupByValue)), 0, $hashLength);
        }

        return $groupByKey;
    }
}
