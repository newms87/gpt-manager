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

        $classification = reset($artifact->json_content);

        foreach($this->taskProcess->inputArtifacts as $inputArtifact) {
            $meta = $inputArtifact->meta;

            $meta['classification'] = $classification;

            $inputArtifact->meta = $meta;
            $inputArtifact->save();
        }
    }
}
