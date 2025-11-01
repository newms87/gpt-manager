<?php

namespace App\Services\Task\Runners;

use App\Models\Agent\AgentThreadRun;
use App\Services\JsonSchema\JSONSchemaDataToDatabaseMapper;
use Newms87\Danx\Exceptions\ValidationError;

class SaveToDatabaseTaskRunner extends BaseTaskRunner
{
    const string RUNNER_NAME = 'Save To Database';

    public function run(): void
    {
        $outputArtifacts = [];
        foreach ($this->taskProcess->inputArtifacts as $inputArtifact) {
            if (!$inputArtifact->schemaDefinition) {
                $this->activity("No schema definition found for $inputArtifact");

                continue;
            }

            static::logDebug("Save to DB: $inputArtifact");
            static::logDebug("Using Schema: $inputArtifact->schemaDefinition");

            $threadRun   = AgentThreadRun::find($inputArtifact->meta['agent_thread_run_id'] ?? null);
            $jsonContent = $inputArtifact->json_content;

            try {
                app(JSONSchemaDataToDatabaseMapper::class)
                    ->setSchemaDefinition($inputArtifact->schemaDefinition)
                    ->saveTeamObjectUsingSchema($inputArtifact->schemaDefinition->schema ?? [], $jsonContent, $threadRun);
            } catch (ValidationError $e) {
                $this->activity('Validation Error: ' . $e->getMessage());

                continue;
            }

            $inputArtifact->name         .= ' (saved to DB)';
            $inputArtifact->json_content = $jsonContent;

            $outputArtifacts[] = $inputArtifact;
        }

        $this->complete($outputArtifacts);
    }
}
