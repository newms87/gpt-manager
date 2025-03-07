<?php

namespace App\Services\Task;

use App\Models\Task\Artifact;
use Log;
use Newms87\Danx\Helpers\ArrayHelper;

class ArtifactsToGroupsMapper
{
	const string
		GROUPING_MODE_SPLIT = 'Split',
		GROUPING_MODE_MERGE = 'Merge',
		GROUPING_MODE_OVERWRITE = 'Overwrite',
		GROUPING_MODE_CONCATENATE = 'Concatenate';

	protected string $groupingMode = self::GROUPING_MODE_CONCATENATE;
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
	 * Overwrite
	 *   - This will overwrite any existing groups with the same key (no guarantee on which group is kept)
	 *   - There will be 1 or more groups w/ 1 or more artifacts per group
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
	public function groupingMode(string $mode = self::GROUPING_MODE_CONCATENATE): static
	{
		$this->groupingMode = $mode;

		return $this;
	}

	public function useConcatenateMode(): static
	{
		return $this->groupingMode(self::GROUPING_MODE_CONCATENATE);
	}

	public function useSplitMode(): static
	{
		return $this->groupingMode(self::GROUPING_MODE_SPLIT);
	}

	public function useMergeMode(): static
	{
		return $this->groupingMode(self::GROUPING_MODE_MERGE);
	}

	public function useOverwriteMode(): static
	{
		return $this->groupingMode(self::GROUPING_MODE_OVERWRITE);
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
	 * @return Artifact[][] An array of groups of artifacts
	 */
	public function map(array $artifacts): array
	{
		// A quick return if no grouping is defined
		if (!$this->splitByFile && $this->groupingMode === self::GROUPING_MODE_CONCATENATE && !$this->fragmentSelector) {
			return ['default' => $artifacts];
		}

		$groups = [];

		foreach($artifacts as $artifact) {
			$keyPrefix = $this->groupingMode === self::GROUPING_MODE_SPLIT ? $artifact->id : '';

			$jsonContent = $artifact->json_content ?? [];
			if ($artifact->text_content) {
				$jsonContent['text_content'] = $artifact->text_content;
			}

			// Use the artifact ID as a key prefix to ensure groups remain distinct across artifacts
			if ($this->fragmentSelector) {
				$fragmentGroups = $this->resolveGroupsByFragment($jsonContent, $this->fragmentSelector, $keyPrefix);
			} elseif ($this->groupingMode === self::GROUPING_MODE_MERGE) {
				$fragmentGroups[$keyPrefix ?: 'default'] = ['merged' => $jsonContent];
			} else {
				$fragmentGroups[$keyPrefix ?: 'default'] = [$jsonContent];
			}

			// Determine how to combine fragment groups based on the selected grouping mode
			$groups = match ($this->groupingMode) {
				// In SPLIT mode, each artifact's groups remain separate since keys are prefixed with the artifact ID
				self::GROUPING_MODE_SPLIT => $groups + $fragmentGroups,

				// In OVERWRITE mode, fragment groups overwrite any existing groups with the same key
				// NOTE: This behaves similarly to SPLIT but differs conceptually since no key prefix is applied, resulting in data being overwritten
				self:: GROUPING_MODE_OVERWRITE => $fragmentGroups + $groups,

				// In MERGE mode, groups with the same key are combined, keeping only unique values
				self::GROUPING_MODE_MERGE => ArrayHelper::mergeArraysRecursivelyUnique($groups, $fragmentGroups),

				// In CONCATENATE mode, append artifacts for any groups w/ the same key
				default => $this->concatenate($groups, $fragmentGroups),
			};
		}

		$groupsOfArtifacts = [];

		foreach($groups as $groupKey => $items) {
			foreach($items as $itemKey => $item) {
				$textContent = $item['text_content'] ?? null;
				unset($item['text_content']);
				$groupsOfArtifacts[$groupKey][$itemKey] = Artifact::create([
					'name'         => "$groupKey:$itemKey",
					'json_content' => $item,
					'text_content' => $textContent,
				]);
			}
		}

		return $this->addFilesToGroups($artifacts, $groupsOfArtifacts);
	}

	/**
	 * For each group key, concatenate the items from the fragment groups into the existing groups
	 */
	protected function concatenate(array $groups, array $fragmentGroups): array
	{
		foreach($fragmentGroups as $key => $group) {
			$groups[$key] = array_merge($groups[$key] ?? [], $group);
		}

		return $groups;
	}

	/**
	 * Add the files from the artifacts to the groups of artifacts
	 *
	 * @param Artifact[]   $artifacts
	 * @param Artifact[][] $groupsOfArtifacts
	 */
	public function addFilesToGroups(array $artifacts, array $groupsOfArtifacts): array
	{
		$allFiles = [];
		foreach($artifacts as $artifact) {
			foreach($artifact->storedFiles as $storedFile) {
				if ($storedFile->transcodes->isNotEmpty()) {
					foreach($storedFile->transcodes as $transcode) {
						$allFiles[$transcode->id] = $transcode;
					}
				} else {
					$allFiles[$storedFile->id] = $storedFile;
				}
			}
		}

		// If no files, nothing to do, just return the original grouping
		if (!$allFiles) {
			return $groupsOfArtifacts;
		}

		// If splitting by file, cross product all files with all groups so each group contains 1 file and each file is a part of all artifact groups
		if ($this->splitByFile) {
			$fileGroups = [];
			foreach($allFiles as $file) {
				// Create an artifact containing only the single file
				$fileArtifact = Artifact::create(['name' => $file->filename]);
				$fileArtifact->storedFiles()->save($file);

				// Append the file to each artifact group
				foreach($groupsOfArtifacts as $groupKey => $artifactGroup) {
					$fileGroups[$groupKey . ':' . $file->id] = array_merge($artifactGroup, [$fileArtifact]);
				}
			}

			return $fileGroups;
		}

		// Create a single artifact that contains all files
		$filesArtifact = Artifact::create(['name' => 'Files']);
		$filesArtifact->storedFiles()->sync(array_keys($allFiles));

		// Append the files artifact to each group
		foreach($groupsOfArtifacts as &$artifactGroup) {
			$artifactGroup[] = $filesArtifact;
		}
		unset($artifactGroup);

		return $groupsOfArtifacts;
	}

	/**
	 * Resolve the groups for the given data based on the fragment selector.
	 * If no fragment selector is given, returns the data as a single group
	 */
	public function resolveGroupsByFragment(array $data, array $fragmentSelector = null, $keyPrefix = ''): array
	{
		if (!$fragmentSelector || empty($fragmentSelector['children'])) {
			$key = $this->getGroupKey($data);

			return [($keyPrefix ? "$keyPrefix:" : '') . $key => [$data]];
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
				$childGroups[$propertyName] = $this->resolveGroupsByFragment($childData, $childSelector);
			} elseif ($childSelector['type'] === 'array') {
				foreach($childData as $item) {
					if (is_scalar($item)) {
						$childGroups[$propertyName][static::getGroupKey($item)] = $item;
					} else {
						$resolvedGroups             = $this->resolveGroupsByFragment($item, $childSelector);
						$childGroups[$propertyName] = ArrayHelper::mergeArraysRecursivelyUnique($resolvedGroups, $childGroups[$propertyName] ?? []);
					}
				}
			} else {
				$baseGroupKey .= ':' . $childData;
			}
		}

		$groups = [($keyPrefix ? "$keyPrefix:" : '') . static::getGroupKey($baseGroupKey) => [$data]];

		// Cross product merge the groups together
		foreach($childGroups as $propertyName => $propertyGroups) {
			$newGroups = [];
			// Property Group Items will be 1 or more items that have matched the same values of the fragment at this path
			// (eg: if the fragment is matching on the city property of an array of addresses, all addresses in the same city will be in the same propertyGroupItems list)
			foreach($propertyGroups as $propertyGroupKey => $propertyGroupItems) {
				foreach($groups as $originalGroupKey => $groupItems) {
					foreach($groupItems as $groupItem) {
						if (is_scalar($propertyGroupItems)) {
							// If the propertyGroupItems is just a scalar value, set the group item property to the scalar value
							// as each scalar value will have exactly item since the scalar value itself determines the group key
							$groupItem[$propertyName] = $propertyGroupItems;
						} else {
							// Otherwise, set the group item property to the list of items resolved for the property.
							// If there is only 1 entry, the array will be converted into a single object to simplify the data structure
							// (eg: for the property addresses, set the list of addresses that match the city)
							// NOTE: Even if the property is address (singular), the propertyGroupItems may be an array of addresses as the fragment may have matched on an array of items
							$groupItem[$propertyName] = count($propertyGroupItems) === 1 ? $propertyGroupItems[0] : $propertyGroupItems;
						}

						if ($this->groupingMode === self::GROUPING_MODE_MERGE) {
							// In merge mode, merge the new item with the current item in the group (if it exists). All groups will have exactly 1 item.
							$mergedItem = ArrayHelper::mergeArraysRecursivelyUnique($groupItem, $newGroups[$originalGroupKey . ':' . $propertyGroupKey]['merged'] ?? []);

							$newGroups[$originalGroupKey . ':' . $propertyGroupKey]['merged'] = $mergedItem;
						} else {
							$newGroups[$originalGroupKey . ':' . $propertyGroupKey][] = $groupItem;
						}
					}
				}
			}
			$groups = $newGroups;
		}

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
