<?php

namespace App\Services\AgentThread;

use App\Models\Agent\Agent;
use App\Models\Agent\AgentThreadMessage;
use App\Models\Agent\AgentThreadRun;
use App\Models\Task\Artifact;
use Newms87\Danx\Traits\HasDebugLogging;
use Newms87\Danx\Helpers\DateHelper;
use Newms87\Danx\Helpers\StringHelper;

class AgentThreadMessageToArtifactMapper
{
    use HasDebugLogging;

    protected string $name      = '';

    protected Agent $agent;

    protected ?AgentThreadRun $threadRun = null;

    protected AgentThreadMessage $message;

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function setThreadRun(AgentThreadRun $threadRun): static
    {
        $this->threadRun = $threadRun;

        return $this;
    }

    public function setMessage(AgentThreadMessage $message): static
    {
        $this->message = $message;
        $this->agent   = $message->agentThread->agent;

        return $this;
    }

    public function map(): ?Artifact
    {
        // Produce the artifact
        $jsonContent = null;
        $textContent = null;

        if ($this->threadRun->response_format === AgentThreadRun::RESPONSE_FORMAT_TEXT) {
            $textContent = $this->message->getCleanContent();
        } else {
            $jsonContent = $this->message->getJsonContent();
        }

        if (!$textContent && !$jsonContent) {
            static::logDebug('Did not produce an artifact: No text or JSON content found in message');

            return null;
        }

        $jsonMeta = $jsonContent['__meta'] ?? [];
        unset($jsonContent['__meta']);

        // Resolve the name of the artifact preferring the meta response from the agent if it is set
        $name = $this->name;
        if (!empty($jsonMeta['name'])) {
            $name = StringHelper::limitText(100, $jsonMeta['name']);
        } elseif (!$name) {
            $name = $this->message->agentThread->name . ' ' . DateHelper::formatDateTime();
        }

        $artifact = Artifact::create([
            'name'         => $name,
            'model'        => $this->agent->model,
            'text_content' => $textContent,
            'json_content' => $jsonContent,
            'meta'         => [
                'agent_thread_run_id' => $this->threadRun?->id,
                'api_response_id'     => $this->message->api_response_id,
            ] + $jsonMeta,
        ]);

        if ($jsonContent) {
            $this->attachCitedSourceFilesToArtifact($artifact);
        }

        static::logDebug("Created $artifact");

        return $artifact;
    }

    /**
     * Attach any source files cited in the artifact data to the artifact
     */
    public function attachCitedSourceFilesToArtifact(Artifact $artifact): void
    {
        $sourceFileIds = $this->flattenSourceFiles($artifact->json_content);
        $artifact->storedFiles()->syncWithoutDetaching($sourceFileIds);
    }

    /**
     * Flatten the data structure to find all file IDs
     */
    private function flattenSourceFiles($data): array
    {
        if (!is_array($data)) {
            return [];
        }

        $fileIds = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $fileIds = array_merge($fileIds, $this->flattenSourceFiles($value));
            } elseif ($key === 'file_id') {
                $fileIds[] = $value;
            }
        }

        return $fileIds;
    }
}
