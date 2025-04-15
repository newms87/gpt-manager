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
        $outputArtifacts = [];
        foreach($this->taskProcess->inputArtifacts as $inputArtifact) {
            if (!$inputArtifact->schemaDefinition) {
                $this->activity("No schema definition found for $inputArtifact");
                continue;
            }

            static::log("Save to DB: $inputArtifact");
            static::log("Using Schema: $inputArtifact->schemaDefinition");

            $threadRun   = AgentThreadRun::find($inputArtifact->meta['agent_thread_run_id'] ?? null);
            $jsonContent = $inputArtifact->json_content;

            try {
                app(JSONSchemaDataToDatabaseMapper::class)
                    ->setSchemaDefinition($inputArtifact->schemaDefinition)
                    ->saveTeamObjectUsingSchema($inputArtifact->schemaDefinition->schema ?? [], $jsonContent, $threadRun);
            } catch(ValidationError $e) {
                $this->activity("Validation Error: " . $e->getMessage());

                continue;
            }

            $outputArtifact = Artifact::create([
                'name'                 => $inputArtifact->name . ' (saved to DB)',
                'json_content'         => $jsonContent,
                'text_content'         => $inputArtifact->text_content,
                'meta'                 => $inputArtifact->meta,
                'schema_definition_id' => $inputArtifact->schema_definition_id,
            ]);

            if ($inputArtifact->storedFiles) {
                $outputArtifact->storedFiles()->sync($inputArtifact->storedFiles->pluck('id'));
            }

            $outputArtifacts[] = $outputArtifact;
        }

        $this->complete($outputArtifacts);
    }
}
