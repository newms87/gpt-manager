<?php

namespace App\Services\Task\Runners;

use App\Models\Agent\AgentThreadRun;
use App\Models\Task\Artifact;
use App\Services\JsonSchema\JSONSchemaDataToDatabaseMapper;
use Newms87\Danx\Exceptions\ValidationError;

class SaveToDatabaseTaskRunner extends BaseTaskRunner
{
    const string RUNNER_NAME = 'Save To Database';

    public function run(): void
    {
        $schemaDefinition = $this->taskProcess->taskDefinitionAgent?->outputSchemaAssociation?->schemaDefinition;

        if (!$schemaDefinition) {
            throw new ValidationError('No schema definition found for Save To Database task. Add an agent and set output schema to save to database.');
        }

        static::log("Saving to database using schema definition $schemaDefinition");
        
        $outputArtifacts = [];
        foreach($this->taskProcess->inputArtifacts as $inputArtifact) {
            $threadRun   = AgentThreadRun::find($inputArtifact->meta['agent_thread_run_id'] ?? null);
            $jsonContent = $inputArtifact->json_content;
            app(JSONSchemaDataToDatabaseMapper::class)
                ->setSchemaDefinition($schemaDefinition)
                ->saveTeamObjectUsingSchema($schemaDefinition->schema ?? [], $jsonContent, $threadRun);

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
