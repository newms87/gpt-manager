<?php

namespace App\Services\Task\Runners;

use Newms87\Danx\Exceptions\ValidationError;

class ClassifierTaskRunner extends AgentThreadTaskRunner
{
    const string RUNNER_NAME = 'Classifier';

    public function run(): void
    {
        $agentThread = $this->setupAgentThread();
        $artifact    = $this->runAgentThread($agentThread);

        if (!$artifact->json_content) {
            throw new ValidationError(static::class . ": No JSON content returned from agent thread");
        }

        $jsonContent    = $artifact->json_content;
        $classification = reset($jsonContent);

        foreach($this->taskProcess->inputArtifacts as $inputArtifact) {
            $meta = $inputArtifact->meta;

            $meta['classification'] = $classification;

            $inputArtifact->meta = $meta;
            $inputArtifact->save();
        }

        // We've added the meta data to the artifacts so re-use the same artifacts and complete the process
        $this->complete($this->taskProcess->inputArtifacts);
    }
}
