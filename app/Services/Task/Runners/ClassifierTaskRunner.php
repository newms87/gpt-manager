<?php

namespace App\Services\Task\Runners;

use Newms87\Danx\Exceptions\ValidationError;

class ClassifierTaskRunner extends AgentThreadTaskRunner
{
    const string RUNNER_NAME = 'Classifier';

    const string CATEGORY_EXCLUDE = '__exclude';

    public function run(): void
    {
        $inputArtifacts = $this->taskProcess->inputArtifacts;

        $agentThread = $this->setupAgentThread($inputArtifacts);
        $artifact    = $this->runAgentThread($agentThread);

        if (!$artifact->json_content) {
            throw new ValidationError(static::class . ": No JSON content returned from agent thread");
        }

        // If the artifact has an excluded flag, then exclude all the artifacts in the input
        if ($this->isExcluded($artifact->json_content)) {
            $this->activity("Classifier excluded the artifacts", 100);
            $this->complete();

            return;
        }

        // Update the metadata classification for input artifacts to the classification returned from the agent
        foreach($inputArtifacts as $inputArtifact) {
            $meta = $inputArtifact->meta;

            $meta['classification'] = $artifact->json_content;

            $inputArtifact->meta = $meta;
            $inputArtifact->save();
        }

        // We've added the metadata to the artifacts so re-use the same artifacts and complete the process
        $this->complete($inputArtifacts);
    }

    /**
     * Recursively check if any of the values in the JSON content has an excluded flag
     */
    public function isExcluded(array $jsonContent): bool
    {
        foreach($jsonContent as $value) {
            if (is_array($value)) {
                if ($this->isExcluded($value)) {
                    return true;
                }
            } elseif (is_string($value) && str_contains($value, self::CATEGORY_EXCLUDE)) {
                return true;
            }
        }

        return false;
    }
}
