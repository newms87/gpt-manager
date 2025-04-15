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

        foreach($groupedArtifacts as $artifactsInGroup) {
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

        if (!$metaFragmentSelector && !$jsonContentFragmentSelector) {
            return ['default' => $this->taskProcess->inputArtifacts];
        }

        $artifactsByGroup = [];

        foreach($this->taskProcess->inputArtifacts as $inputArtifact) {
            $jsonContentKey = $inputArtifact->getFlattenedJsonFragmentValuesString($jsonContentFragmentSelector);
            $metaKey        = $inputArtifact->getFlattenedMetaFragmentValuesString($metaFragmentSelector);
            $groupKey       = "$jsonContentKey;$metaKey";

            $artifactsByGroup[$groupKey][] = $inputArtifact;
        }

        return $artifactsByGroup;
    }
}
