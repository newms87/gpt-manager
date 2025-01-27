<?php

namespace App\Services\AgentThread;

use App\Models\Agent\Agent;
use App\Models\Agent\Message;
use App\Models\Workflow\Artifact;
use Illuminate\Support\Facades\Log;
use Newms87\Danx\Helpers\DateHelper;

class AgentThreadMessageToArtifactMapper
{
    protected string  $name = '';
    protected Agent   $agent;
    protected Message $message;

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function setMessage(Message $message): static
    {
        $this->message = $message;
        $this->agent   = $message->thread->agent;

        return $this;
    }

    public function map(): Artifact|null
    {
        // Product the artifact
        $data    = null;
        $content = null;

        if ($this->agent->response_format === Agent::RESPONSE_FORMAT_TEXT) {
            $content = $this->message->getCleanContent();
        } else {
            $data = $this->message->getJsonContent();
        }

        if (!$content && !$data) {
            Log::debug("Did not produce an artifact: No text or JSON content found in message");

            return null;
        }

        $artifact = Artifact::create([
            'name'    => $this->name ?: $this->message->thread->name . ' ' . DateHelper::formatDateTime(),
            'model'   => $this->agent->model,
            'content' => $content,
            'data'    => $data,
        ]);

        if ($data) {
            $this->attachCitedSourceFilesToArtifact($artifact);
        }

        Log::debug("Created $artifact");

        return $artifact;
    }

    /**
     * Attach any source files cited in the artifact data to the artifact
     */
    public function attachCitedSourceFilesToArtifact(Artifact $artifact): void
    {
        $sourceFileIds = $this->flattenSourceFiles($artifact->data);
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

        foreach($data as $key => $value) {
            if (is_array($value)) {
                $fileIds = array_merge($fileIds, $this->flattenSourceFiles($value));
            } elseif ($key === 'file_id') {
                $fileIds[] = $value;
            }
        }

        return $fileIds;
    }
}
