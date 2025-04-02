<?php

namespace App\Services\Task\Runners;

use App\Services\Task\ArtifactsToGroupsMapper;

class SplitByJsonContentTaskRunner extends BaseTaskRunner
{
    const string RUNNER_NAME = 'Split By Json Content';

    public function run(): void
    {
        $this->activity('Splitting JSON content', 1);

        $groupingKeys = [];
        foreach($this->taskRun->taskDefinition->schemaAssociations as $schemaAssociation) {
            $groupingKeys[] = $schemaAssociation->schemaFragment->fragment_selector;
        }

        $outputArtifacts = (new ArtifactsToGroupsMapper())->useSplitMode()->setGroupingKeys($groupingKeys)->map($this->taskProcess->inputArtifacts);

        $this->activity('JSON content split successfully', 100);

        $this->complete(collect($outputArtifacts)->flatten());
    }
}
