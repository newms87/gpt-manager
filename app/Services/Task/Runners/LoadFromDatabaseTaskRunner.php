<?php

namespace App\Services\Task\Runners;

use App\Models\Task\Artifact;
use App\Repositories\TeamObjectRepository;
use App\Resources\TeamObject\TeamObjectForAgentsResource;
use Newms87\Danx\Exceptions\ValidationError;

class LoadFromDatabaseTaskRunner extends BaseTaskRunner
{
    const string RUNNER_NAME = 'Load From Database';

    public function run(): void
    {
        $uniqueTeamObjects = [];
        foreach($this->taskProcess->inputArtifacts as $inputArtifact) {
            $type = $inputArtifact->json_content['type'] ?? null;
            $id   = $inputArtifact->json_content['id'] ?? null;

            if (!$type || !$id) {
                $this->activity("No ID or type found, skipping $inputArtifact");
                continue;
            }

            $uniqueTeamObjects[$id] = $type;
        }

        // Handle the base case where there were no identified objects to load
        if (!$uniqueTeamObjects) {
            $this->activity("No unique team objects found", 100);

            return;
        }

        $outputArtifacts    = [];
        $percentPerArtifact = 90 / count($uniqueTeamObjects);
        $percent            = 10;

        foreach($uniqueTeamObjects as $id => $type) {

            $this->activity("Loading $type ($id)", $percent);

            $teamObject = app(TeamObjectRepository::class)->loadTeamObject($type, $id);

            if (!$teamObject) {
                throw new ValidationError("Could not find $type with ID $id");
            }

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
