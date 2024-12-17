<?php

namespace App\WorkflowTools;

use App\Models\Workflow\WorkflowJob;
use App\Models\Workflow\WorkflowJobDependency;
use App\Models\Workflow\WorkflowJobRun;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Newms87\Danx\Helpers\ArrayHelper;
use Newms87\Danx\Helpers\StringHelper;

trait ResolvesDependencyArtifactsTrait
{
    const int MAX_KEY_LENGTH  = 100;
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
     * Groupings are based on each dependencies' group_by field
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
        Log::debug("Building artifact groups for {$dependency->dependsOn->name} => {$dependency->workflowJob->name}");

        $groups = [];

        $schemaFields = $dependency->include_fields ?: $dependency->dependsOn->getResponseFields();

        $artifacts = $workflowJobRun->artifacts()->get();

        foreach($artifacts as $artifact) {
            Log::debug("Extracting groups from $artifact");

            $artifactData = $artifact->data ?: [];

            // If a json encoded string or primitive was set, just treat it like content
            if (!is_array($artifactData)) {
                $artifactData = ['content' => $artifactData];
            }

            // Special case for string content artifacts, just treat all like JSON responses but with a {content: ...} entry
            // NOTE: Overwrites any other content set in the special key for content!
            if ($artifact->content) {
                $artifactData['content'] = $artifact->content;
            }

            // Set the special key for files
            $storedFiles = $artifact->storedFiles()->get();
            if ($storedFiles->isNotEmpty()) {
                $artifactData['files'] = $storedFiles->toArray();
            }

            $includedFields = $dependency->force_schema ? $schemaFields : null;

            if ($dependency->group_by) {
                Log::info("Extracting group by " . json_encode($dependency->group_by));
                $groupsOfItemSets = ArrayHelper::crossProductExtractData($artifactData, $dependency->group_by);

                foreach($groupsOfItemSets as $itemSet) {
                    $groupKey = $this->generateGroupKey($itemSet);
                    $data     = $this->extractGroupsFromArtifactData($artifactData, $itemSet, $includedFields);

                    if ($data) {
                        Log::debug("Resolved key data $groupKey: " . strlen(json_encode($data)) . " bytes");
                        $groups[$groupKey][] = $data;
                    } else {
                        Log::debug("Skipping $groupKey: No data in group");
                    }
                }
            } else {
                Log::debug("Extracting default group");
                $data = $this->extractDefaultGroupFromArtifactData($artifactData, $dependency->force_schema ? $schemaFields : null);

                if ($data) {
                    Log::debug("Resolved default group: " . strlen(json_encode($data)) . " bytes");
                    $groups['default'][] = $data;
                } else {
                    Log::debug("Skipping default group: No data in group");
                }
            }
        }

        // Order the groups by the order_by clause
        if ($dependency->order_by) {
            Log::debug("Ordering groups by {$dependency->order_by['name']} {$dependency->order_by['direction']}");

            // First sort each group by the order_by clause
            ArrayHelper::sortByNestedData($groups, '*.' . $dependency->order_by['name'], $dependency->order_by['direction']);

            // Then sort the items in each group by the order clause
            foreach($groups as &$group) {
                // The groups here are nested in an array of entries where each entry is a group. The group is an array of objects. For that reason we need the *. prefix
                // to sort by the values of the objects in the groups.
                ArrayHelper::sortByNestedData($group, $dependency->order_by['name'], $dependency->order_by['direction']);
            }
            unset($group);
        }

        return $groups;
    }

    public function extractDefaultGroupFromArtifactData(array $artifactData, $includedFields = []): array|string|null
    {
        // Special case for content - only artifacts, just return the content as plain text
        if (count($artifactData) === 1 && !empty($artifactData['content'])) {
            return $artifactData['content'];
        }

        if ($includedFields) {
            $artifactData = ArrayHelper::extractNestedData($artifactData, $includedFields);
        }

        return $artifactData;
    }

    public function extractGroupsFromArtifactData(array $artifactData, $itemSet, $includedFields = []): array|string|null
    {
        foreach($itemSet as $itemIndex => $itemValue) {
            $artifactData = ArrayHelper::filterNestedData($artifactData, $itemIndex, $itemValue);
            if (!$artifactData) {
                Log::debug("No data for item $itemIndex. Omitting record " . (is_array($itemValue) ? $this->generateGroupKey($itemValue) : $itemValue));

                return [];
            }
        }

        if ($includedFields) {
            $artifactData = ArrayHelper::extractNestedData($artifactData, $includedFields);
        }

        return $artifactData;
    }

    /**
     * Performs a Cartesian product (ie cross product) on the artifact groups to generate tuples
     */
    public function generateArtifactGroupTuples(array $dependencyArtifactGroups): array
    {
        if (!$dependencyArtifactGroups) {
            return ['default' => [['content' => 'Follow the prompt']]];
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
                        $newGroupTuples[$tupleKey . ' | ' . $artifactGroupKey] = array_merge($tupleArtifacts, $artifacts);
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
            $groupKeyValue = $value;
            if (is_array($groupKeyValue)) {
                $bestNameValue = $groupKeyValue['name'] ?? $groupKeyValue['title'] ?? $groupKeyValue['id'] ?? reset($groupKeyValue);
                if (is_array($bestNameValue)) {
                    $groupKeyValue = substr(md5(json_encode($bestNameValue)), 0, 6);
                } else {
                    $groupKeyValue = substr($bestNameValue, 0, 20);
                }
            }
            $tmpKey = ($groupByKey ? "$groupByKey," : '') . $key . ':' . ($groupKeyValue ?: 'default');

            // If we've exceeded the key length, we need to add a hash to make the key unique
            if ($groupByKey && strlen($tmpKey) > static::MAX_KEY_LENGTH) {
                $requiresHash = true;
                break;
            }

            $groupByKey = $tmpKey;
        }

        $groupByKey = $currentKey ? "$currentKey | $groupByKey" : $groupByKey;

        if ($requiresHash || strlen($groupByKey) > static::MAX_KEY_LENGTH) {
            $hash       = '#' . substr(md5(json_encode($groupedItems)), 0, static::MAX_HASH_LENGTH);
            $groupByKey = StringHelper::limitText(static::MAX_KEY_LENGTH, $groupByKey, $hash);
        }

        return $groupByKey;
    }
}
