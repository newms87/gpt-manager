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
use Newms87\Danx\Helpers\StringHelper;

trait ResolvesDependencyArtifactsTrait
{
    const int MAX_KEY_LENGTH  = 30;
    const int MAX_HASH_LENGTH = 6;

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
            Log::error("Failed to resolve dependency artifacts $workflowJob: " . $e->getMessage() . ' -- ' . $e->getFile() . '@' . $e->getLine());

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
            $artifactData = $artifact->data ?: [];

            // Special case for string content artifacts, just treat all like JSON responses but with a {content: ...} entry
            if ($artifact->content) {
                $artifactData['content'] = $artifact->content;
            }

            if (!$dependency->group_by) {
                // Special case for content - only artifacts, just return the content as plain text
                if ($artifact->content && !$artifact->data) {
                    $groups['default'][] = $artifact->content;
                } else {
                    $groups['default'][] = ArrayHelper::extractNestedData($artifactData, $dependency->include_fields);
                }
                continue;
            }

            $groupsOfItemSets = ArrayHelper::crossProductExtractData($artifactData, $dependency->group_by);
            $includedData     = ArrayHelper::extractNestedData($artifactData, $dependency->include_fields);

            dump('groupsofItems', $groupsOfItemSets);
            foreach($groupsOfItemSets as $itemSet) {
                $groupKey     = $this->generateGroupKey($itemSet);
                $resolvedData = $includedData;

                // For each itemSet index (ie: name, services.*.name, services.*.desc, etc.)
                // Need to resolve the data from the includedData at each level of the itemIndex to a single record
                // Each itemIndex will reduce the level it points (ie: services.*.name points to the services array level) to in the resolvedData to fewer records.
                // If the array level is already at 1, then we can convert that array level to singular and replace the index key with the singular name
                // We must start at the deepest level first and work our way up
                // If there are multiple records left after performing the filtering, then just choose the first one as this is ambiguous data grouping


                foreach($itemSet as $itemIndex => $itemValue) {
                    if (preg_match("/\\.\\*.*$/", $itemIndex)) {
                        // If the item index is a wildcard, remove it in favor of a singular version of the field
                        $arrayIndex = preg_replace("/\\.\\*.*$/", '', $itemIndex);
                        $arrayValue = data_get($resolvedData, $arrayIndex);
                        foreach($arrayValue as $item) {
                            if ($item) {
                                $groups[$groupKey][] = $item;
                            }
                        }

                        dump('got array', $arrayIndex, $arrayValue);
                        // For the group index field, remove it in favor a singular version of the field
                        data_forget($resolvedData, $arrayIndex);
                        data_set($resolvedData, Str::singular($arrayIndex), $arrayValue);
                    } else {
                        // For the group index field, remove it in favor a singular version of the field
                        data_forget($resolvedData, $itemIndex);
                        data_set($resolvedData, Str::singular($itemIndex), $itemValue);
                    }
                }

                $groups[$groupKey][] = $resolvedData;
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
            return ['default' => []];
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
     * Generates a unique key for grouping data based on the groupedItems.
     * If the groupByValue is an array, it will be sorted and concatenated into a string.
     * If the key is too long, a hash will be added to make the key unique.
     * If the groupByValue is an array of arrays, returns false, as keys should be generated only for the children.
     */
    public function generateGroupKey(array $groupedItems, $currentKey = ''): string|false
    {
        // A flag to indicate there is data not defined in the key,
        // so we need to add a hash to make the key unique to the data
        $requiresHash = false;


        ArrayHelper::recursiveKsort($groupedItems);
        $groupByKey = '';
        foreach($groupedItems as $key => $value) {
            if (is_array($value)) {
                // Can't make a readable unique key from an array value, so just make a hash of all the data
                $requiresHash = true;
            }
            $tmpKey = ($groupByKey ? "$groupByKey," : '') . (is_array($value) ? $key : "$key:$value");

            // If we've exceeded the key length, we need to add a hash to make the key unique
            if ($groupByKey && strlen($tmpKey) > static::MAX_KEY_LENGTH) {
                $requiresHash = true;
                break;
            }

            $groupByKey = $tmpKey;
        }

        $groupByKey = $currentKey ? "$currentKey|$groupByKey" : $groupByKey;
        if (strlen($groupByKey) > static::MAX_KEY_LENGTH) {
            $requiresHash = true;
        }
        if ($requiresHash) {
            $hash       = '#' . substr(md5(json_encode($groupedItems)), 0, static::MAX_HASH_LENGTH);
            $groupByKey = StringHelper::limitText(static::MAX_KEY_LENGTH, $groupByKey, $hash);
        }

        return $groupByKey;
    }
}
