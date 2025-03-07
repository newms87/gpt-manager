<?php

namespace App\Services\AgentThread;

use App\Models\Task\Artifact;
use App\Services\JsonSchema\JsonSchemaService;

class ArtifactFilter
{
    private ?Artifact $artifact           = null;
    private bool      $includePageNumbers = false;
    private bool      $includeText        = false;
    private bool      $includeFiles       = false;
    private bool      $includeJson        = false;
    private array     $fragmentSelector   = [];

    public function setArtifact(Artifact $artifact): static
    {
        $this->artifact = $artifact;

        return $this;
    }

    public function includePageNumbers(bool $included = true): static
    {
        $this->includePageNumbers = $included;

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

    public function includeJson(bool $included = true, array $fragmentSelector = []): static
    {
        $this->includeJson      = $included;
        $this->fragmentSelector = $fragmentSelector;

        return $this;
    }

    public function isTextOnly(): bool
    {
        return $this->includeText && !$this->includeFiles && !$this->includeJson;
    }

    public function getTextContent(): ?string
    {
        $textContent = $this->artifact->text_content;

        if ($this->includePageNumbers && $this->artifact->storedFiles) {
            $pageNumbers = [];
            foreach($this->artifact->storedFiles as $file) {
                if ($file->page_number) {
                    $pageNumbers[$file->page_number] = $file->page_number;
                }
            }

            if ($pageNumbers) {
                sort($pageNumbers);
                $textContent = "### Content for " . (count($pageNumbers) > 1 ? 'pages' : 'page') . ' ' . implode(', ', $pageNumbers) . "\n\n" . $textContent;
            }
        }

        return $textContent;
    }

    public function getFilteredData(): ?array
    {
        if ($this->fragmentSelector) {
            return (new JsonSchemaService)->useId()->filterDataByFragmentSelector($this->artifact->json_content ?? [], $this->fragmentSelector);
        }

        return $this->artifact->json_content;
    }

    public function filter(): array|string|null
    {
        if (!$this->artifact) {
            return null;
        }

        if ($this->isTextOnly()) {
            return $this->getTextContent();
        } else {
            $data = [];

            if ($this->includeText) {
                $data['text_content'] = $this->getTextContent();
            }

            if ($this->includeFiles) {
                $data['files'] = $this->artifact->storedFiles;
            }

            if ($this->includeJson) {
                $data['json_content'] = $this->getFilteredData();
            }

            return $data;
        }
    }
}
