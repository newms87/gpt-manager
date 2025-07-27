<?php

namespace App\Services\Task\Runners;

use App\Repositories\ThreadRepository;
use App\Services\Task\ClassificationDeduplicationService;
use App\Services\Task\ClassificationVerificationService;
use Newms87\Danx\Exceptions\ValidationError;

class ClassifierTaskRunner extends AgentThreadTaskRunner
{
    const string RUNNER_NAME = 'Classifier';

    const string CATEGORY_EXCLUDE = '__exclude';

    public function run(): void
    {
        // Check if this is a classification property deduplication process
        $classificationProperty = $this->taskProcess->meta['classification_property'] ?? null;
        if ($classificationProperty) {
            static::log("Running classification deduplication for property: $classificationProperty");

            $artifacts = $this->taskRun->outputArtifacts;
            app(ClassificationDeduplicationService::class)->deduplicateClassificationProperty($artifacts, $classificationProperty);

            $this->complete();

            return;
        }

        // Check if this is a classification property verification process
        $verificationProperty = $this->taskProcess->meta['classification_verification_property'] ?? null;
        if ($verificationProperty) {
            static::log("Running classification verification for property: $verificationProperty");

            $artifacts = $this->taskRun->outputArtifacts;
            app(ClassificationVerificationService::class)->verifyClassificationProperty($artifacts, $verificationProperty);

            $this->complete();

            return;
        }

        $inputArtifacts = $this->taskProcess->inputArtifacts;

        $agentThread = $this->setupAgentThread($inputArtifacts);

        app(ThreadRepository::class)->addMessageToThread($agentThread, "If the only content in the artifact is 'Excluded...' or is very obviously all redacted content and there is no other content of interest, then set the category values to __exclude so the artifacts will be ignored entirely");

        $artifact = $this->runAgentThread($agentThread);

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

    /**
     * Called after all parallel processes have completed
     * Check the phase: create deduplication processes first, then verification processes
     */
    public function afterAllProcessesCompleted(): void
    {
        parent::afterAllProcessesCompleted();

        // Check if any TaskProcesses in this TaskRun have classification_property or verification meta set
        $hasPropertyProcesses = $this->taskRun->taskProcesses()
            ->whereNotNull('meta->classification_property')
            ->exists();

        $hasVerificationProcesses = $this->taskRun->taskProcesses()
            ->whereNotNull('meta->classification_verification_property')
            ->exists();

        if ($hasVerificationProcesses) {
            static::log("TaskProcess with classification_verification_property meta found - verification phase completed");
            return;
        }

        if ($hasPropertyProcesses) {
            static::log("TaskProcess with classification_property meta found - creating verification processes");

            try {
                app(ClassificationVerificationService::class)->createVerificationProcessesForTaskRun($this->taskRun);
            } catch(\Exception $e) {
                static::log("Error creating classification verification processes: " . $e->getMessage());
            }

            return;
        }

        static::log("No classification property processes found - creating deduplication processes");

        try {
            app(ClassificationDeduplicationService::class)->createDeduplicationProcessesForTaskRun($this->taskRun);
        } catch(\Exception $e) {
            static::log("Error creating classification deduplication processes: " . $e->getMessage());
        }
    }
}
