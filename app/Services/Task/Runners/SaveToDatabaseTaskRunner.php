<?php

namespace App\Services\Task\Runners;

use App\Models\Task\Artifact;
use App\Repositories\TeamObjectRepository;
use App\Resources\TeamObject\TeamObjectForAgentsResource;

class SaveToDatabaseTaskRunner extends BaseTaskRunner
{
    const string RUNNER_NAME = 'Save To Database';

    public function run(): void
    {
        $outputArtifacts = [];
        foreach($this->taskProcess->inputArtifacts as $inputArtifact) {
            $type = $inputArtifact->json_content['type'] ?? null;
            $id   = $inputArtifact->json_content['id'] ?? null;

            if (!$type || !$id) {
                static::log("No ID or type found, skipping $inputArtifact");
                continue;
            }

            $this->activity("Loading $type ($id)", $percent);

            $teamObject        = app(TeamObjectRepository::class)->loadTeamObject($type, $id);
            $loadedTeamObject  = TeamObjectForAgentsResource::make($teamObject);
            $outputArtifacts[] = Artifact::create([
                'name'         => $teamObject->name . ' (' . $teamObject->id . ')',
                'json_content' => $loadedTeamObject,
            ]);

            $percent += $percentPerArtifact;
        }

        $this->complete($outputArtifacts);
    }
}
