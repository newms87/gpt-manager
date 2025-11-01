<?php

namespace App\Services\Task\Runners;

use App\Services\Task\ArtifactsMergeService;

class MergeArtifactsTaskRunner extends BaseTaskRunner
{
    const string RUNNER_NAME = 'Merge Artifacts';

    public function run(): void
    {
        $groupedArtifacts = $this->groupArtifacts();

        $outputArtifacts = [];

        foreach ($groupedArtifacts as $artifactsInGroup) {
            $outputArtifacts[] = app(ArtifactsMergeService::class)->merge($artifactsInGroup);
        }

        $this->complete($outputArtifacts);
    }

    /**
     * Group artifacts by their JSON content and meta fragment values.
     */
    public function groupArtifacts(): array
    {
        $jsonContentFragmentSelector = $this->config('json_content_fragment_selector') ?: [];
        $metaFragmentSelector        = $this->config('meta_fragment_selector') ?: [];

        $artifacts = $this->taskProcess->inputArtifacts;

        static::logDebug('Merging ' . $artifacts->count() . " artifacts:\n" . $artifacts->pluck('id')->implode(','));

        if (!$metaFragmentSelector && !$jsonContentFragmentSelector) {
            static::logDebug('No fragment selectors applied, merging full artifacts...');

            return ['default' => $artifacts];
        }

        $artifactsByGroup = [];

        foreach ($artifacts as $inputArtifact) {
            $jsonContentKey = $inputArtifact->getFlattenedJsonFragmentValuesString($jsonContentFragmentSelector);
            $metaKey        = $inputArtifact->getFlattenedMetaFragmentValuesString($metaFragmentSelector);
            $groupKey       = "$jsonContentKey;$metaKey";
            static::logDebug("Group key for Artifact $inputArtifact->id: $groupKey");

            $artifactsByGroup[$groupKey][] = $inputArtifact;
        }

        return $artifactsByGroup;
    }
}
