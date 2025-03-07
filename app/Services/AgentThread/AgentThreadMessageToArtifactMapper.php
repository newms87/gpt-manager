<?php

namespace App\Services\AgentThread;

use App\Models\Agent\Agent;
use App\Models\Agent\AgentThreadMessage;
use App\Models\Task\Artifact;
use Illuminate\Support\Facades\Log;
use Newms87\Danx\Helpers\DateHelper;

class AgentThreadMessageToArtifactMapper
{
	protected string             $name = '';
	protected Agent              $agent;
	protected AgentThreadMessage $message;

	public function setName(string $name): static
	{
		$this->name = $name;

		return $this;
	}

	public function setMessage(AgentThreadMessage $message): static
	{
		$this->message = $message;
		$this->agent   = $message->agentThread->agent;

		return $this;
	}

	public function map(): Artifact|null
	{
		// Produce the artifact
		$jsonContent = null;
		$textContent = null;

		if ($this->agent->response_format === Agent::RESPONSE_FORMAT_TEXT) {
			$textContent = $this->message->getCleanContent();
		} else {
			$jsonContent = $this->message->getJsonContent();
		}

		if (!$textContent && !$jsonContent) {
			Log::debug("Did not produce an artifact: No text or JSON content found in message");

			return null;
		}

		$artifact = Artifact::create([
			'name'         => $this->name ?: $this->message->agentThread->name . ' ' . DateHelper::formatDateTime(),
			'model'        => $this->agent->model,
			'text_content' => $textContent,
			'json_content' => $jsonContent,
		]);

		if ($jsonContent) {
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
