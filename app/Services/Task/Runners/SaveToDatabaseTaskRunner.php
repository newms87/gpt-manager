<?php

namespace App\Services\Task\Runners;

use App\Models\Agent\AgentThreadRun;
use App\Models\Task\Artifact;
use App\Services\JsonSchema\JSONSchemaDataToDatabaseMapper;

class SaveToDatabaseTaskRunner extends BaseTaskRunner
{
    const string RUNNER_NAME = 'Save To Database';

    public function run(): void
    {
        $outputArtifacts = [];
        foreach($this->taskProcess->inputArtifacts as $inputArtifact) {
            if (!$inputArtifact->schemaDefinition) {
                static::log("No schema definition found for $inputArtifact");
                continue;
            }

            static::log("Save to DB: $inputArtifact");
            static::log("Using Schema: $inputArtifact->schemaDefinition");

            $threadRun   = AgentThreadRun::find($inputArtifact->meta['agent_thread_run_id'] ?? null);
            $jsonContent = $inputArtifact->json_content;
            app(JSONSchemaDataToDatabaseMapper::class)
                ->setSchemaDefinition($inputArtifact->schemaDefinition)
                ->saveTeamObjectUsingSchema($inputArtifact->schemaDefinition->schema ?? [], $jsonContent, $threadRun);

            $outputArtifacts[] = Artifact::create([
                'name'                 => $inputArtifact->name . ' (saved to DB)',
                'json_content'         => $jsonContent,
                'meta'                 => $inputArtifact->meta,
                'schema_definition_id' => $inputArtifact->schema_definition_id,
            ]);
        }

        $this->complete($outputArtifacts);
    }
}
