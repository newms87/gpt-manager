<?php

namespace App\Services\Task\Runners;

use App\Services\Task\ArtifactsToGroupsMapper;

class SplitArtifactsTaskRunner extends BaseTaskRunner
{
    const string RUNNER_NAME = 'Split Artifacts';

    public function run(): void
    {
        $this->activity('Splitting artifacts', 1);

        $groupingKeys = [];
        foreach($this->taskDefinition->schemaAssociations as $schemaAssociation) {
            $groupingKeys[] = $schemaAssociation->schemaFragment->fragment_selector;
        }

        $outputArtifacts = (new ArtifactsToGroupsMapper())->useSplitMode()->setGroupingKeys($groupingKeys)->map($this->taskProcess->inputArtifacts);

        $this->activity('Artifacts split successfully', 100);

        $this->complete(collect($outputArtifacts)->flatten());
    }
}
