<?php

namespace App\Services\Task;

use App\Models\Workflow\Artifact;
use Log;
use Newms87\Danx\Helpers\ArrayHelper;

class ArtifactsToGroupsMapper
{
    const string
        GROUPING_MODE_SPLIT = 'Split',
        GROUPING_MODE_MERGE = 'Merge',
        GROUPING_MODE_COMBINE = 'Combine';

    protected string $groupingMode = self::GROUPING_MODE_COMBINE;
    protected bool   $splitByFile  = false;

    /** @var array An array of schema fragment selectors. The resolved values from the artifacts' data will define the group key */
    protected array $fragmentSelector = [];

    /**
     * Set the mode for grouping artifacts together.
     *
     * Combine
     *   - The default grouping mode.
     *   - Takes all artifacts (w/ groups) and puts them into the default group
     *   - Guarantees that there will always be 1 group created w/ 1 or more artifacts
     *
     * Merge
     *   - This will merge the data of each group together to create a single artifact for each unique group
     *   - There will be 1 artifact per group
     *
     * Split
     *   - This will create at least 1 group for each artifact given.
     *   - Each artifact will also be split by the other defined grouping keys
     *   - There will be 1 or more groups w/ 1 or more artifacts per group
     *
     * Split example:
     *  Artifact A has 2 groups
     *  Artifact B has 4 groups
     *  Artifact C has 1 group
     *  --- 7 groups will be returned
     */
    public function groupingMode(string $mode = self::GROUPING_MODE_COMBINE): static
    {
        $this->groupingMode = $mode;

        return $this;
    }

    /**
     * Enable splitting the groups by file. This will create a group for each file in the artifact. The files will take
     * the cross product of any other defined groups so each group will be split into the number of files in the
     * artifact.
     *
     * For example, if the artifact has 3 files and the grouping keys resolve to 2 groups, the result will be 6 groups
     */
    public function splitByFile(bool $enabled = true): static
    {
        $this->splitByFile = $enabled;

        return $this;
    }

    /**
     * Set the grouping keys for the mapper. The resolved values from the artifacts' data will define the number of
     * groups and the group key for each item in the group.
     *
     * @param array $groupingKeys An array of schema fragment selectors.
     */
    public function setGroupingKeys(array $groupingKeys): static
    {
        $this->fragmentSelector = ArrayHelper::mergeArraysRecursivelyUnique(...$groupingKeys);

        return $this;
    }

    /**
     * @param Artifact[] $artifacts
     * @return Artifact[][]
     */
    public function map(array $artifacts): array
    {
        $groups = [];

        foreach($artifacts as $artifact) {
            // Set the artifact ID as the key prefix to keep groups separate across artifacts
            $keyPrefix      = $this->groupingMode === self::GROUPING_MODE_SPLIT ? ($artifact->id . ':') : '';
            $fragmentGroups = $this->resolveGroupsByFragment($artifact->json_content, $this->fragmentSelector, $keyPrefix);

            // When splitting by artifact, just concatenate the groups of each artifact together
            $groups = match ($this->groupingMode) {
                // Split mode, all keys will be unique across artifacts since we're using the artifact ID as the key prefix
                self::GROUPING_MODE_SPLIT => $groups + $fragmentGroups,

                // Merge mode, for all groups with the same key, merge the data together and filter unique values
                self::GROUPING_MODE_MERGE => ArrayHelper::mergeArraysRecursivelyUnique($groups, $fragmentGroups),

                // Combine mode, just concat the groups together throwing away any existing groups with the same key
                // NOTE: this is technically the same operation as SPLIT, but semantically it is different because there is no key prefix being used.
                default => $groups + $fragmentGroups,
            };
        }

        $groupsOfArtifacts = [];

        foreach($groups as $groupKey => $items) {
            foreach($items as $itemKey => $item) {
                $groupsOfArtifacts[$groupKey][$itemKey] = Artifact::create([
                    'name'         => "$groupKey:$itemKey",
                    'json_content' => $item,
                ]);
            }
        }

        return $groupsOfArtifacts;
    }

    public function resolveFiles(Artifact $artifact): array
    {
        return [];
    }

    /**
     * Resolve the groups for the given data based on the fragment selector.
     * If no fragment selector is given, returns the data as a single group
     */
    public function resolveGroupsByFragment(array $data, array $fragmentSelector = null, $keyPrefix = ''): array
    {
        if (!$fragmentSelector || empty($fragmentSelector['children'])) {
            $key = $this->getGroupKey($data);

            return [$keyPrefix . $key => $data];
        }

        $baseGroupKey = '';
        $childGroups  = [];

        foreach($fragmentSelector['children'] as $propertyName => $childSelector) {
            if (!isset($data[$propertyName])) {
                continue;
            }

            $childData = $data[$propertyName];

            // If the types do not match, just ignore this value
            if (in_array($childSelector['type'], ['array', 'object']) && !is_array($childData)) {
                Log::warning("WARNING: Ignoring property $propertyName because the type does not match the selector type: $childSelector[type]: " . json_encode($childData));
                continue;
            }

            if ($childSelector['type'] === 'object') {
                $resolvedGroups             = $this->resolveGroupsByFragment($childData, $childSelector);
                $childGroups[$propertyName] = ArrayHelper::mergeArraysRecursivelyUnique($resolvedGroups, $childGroups[$propertyName] ?? []);
            } elseif ($childSelector['type'] === 'array') {
                foreach($childData as $item) {
                    $resolvedGroups             = $this->resolveGroupsByFragment($item, $childSelector);
                    $childGroups[$propertyName] = ArrayHelper::mergeArraysRecursivelyUnique($resolvedGroups, $childGroups[$propertyName] ?? []);
                }
            } else {
                $baseGroupKey .= ':' . $childData;
            }
        }

        $groups = [$keyPrefix . static::getGroupKey($baseGroupKey) => $data];

        dump("Groupings: ", $groups, 'child groups', $childGroups);

        // Cross product merge the groups together
        foreach($childGroups as $propertyName => $propertyGroups) {
            $newGroups = [];
            foreach($propertyGroups as $propertyGroupKey => $propertyGroup) {
                foreach($groups as $originalGroupKey => $groupData) {
                    // Set the groups property to the
                    $groupData[$propertyName]                               = $propertyGroup;
                    $newGroups[$originalGroupKey . ':' . $propertyGroupKey] = $groupData;
                }
            }
            $groups = $newGroups;
        }

        dump("RETURN", $groups);

        return $groups;
    }

    /**
     * Get the group key for the given group. This will be used to determine if the group already exists in the list
     */
    public static function getGroupKey(array|string $group): string
    {
        return md5(is_string($group) ? $group : json_encode($group));
    }
}
