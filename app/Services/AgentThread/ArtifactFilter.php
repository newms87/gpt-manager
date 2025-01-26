<?php

namespace App\Services\AgentThread;

use App\Models\Workflow\Artifact;
use App\Services\JsonSchema\JsonSchemaService;

class ArtifactFilter
{
    private ?Artifact $artifact         = null;
    private bool      $includeText      = false;
    private bool      $includeFiles     = false;
    private bool      $includeData      = false;
    private array     $fragmentSelector = [];

    public function setArtifact(Artifact $artifact): static
    {
        $this->artifact = $artifact;

        return $this;
    }

    public function includeText(bool $included = true): static
    {
        $this->includeText = $included;

        return $this;
    }

    public function includeFiles(bool $included = true): static
    {
        $this->includeFiles = $included;

        return $this;
    }

    public function includeData(bool $included = true, array $fragmentSelector = []): static
    {
        $this->includeData      = $included;
        $this->fragmentSelector = $fragmentSelector;

        return $this;
    }

    public function isTextOnly(): bool
    {
        return $this->includeText && !$this->includeFiles && !$this->includeData;
    }

    public function getFilteredData(): ?array
    {
        if ($this->fragmentSelector) {
            return (new JsonSchemaService)->filterDataByFragmentSelector($this->artifact->data, $this->fragmentSelector);
        }

        return $this->artifact->data;
    }

    public function filter(): array|string|null
    {
        if (!$this->artifact) {
            return null;
        }

        if ($this->isTextOnly()) {
            return $this->artifact->content;
        } else {
            $data = [];

            if ($this->includeText) {
                $data['content'] = $this->artifact->content;
            }

            if ($this->includeFiles) {
                $data['files'] = $this->artifact->storedFiles;
            }

            if ($this->includeData) {
                $data['data'] = $this->getFilteredData();
            }

            return $data;
        }
    }
}
