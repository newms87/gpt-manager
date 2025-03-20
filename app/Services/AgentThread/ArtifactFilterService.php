<?php

namespace App\Services\AgentThread;

use App\Models\Task\Artifact;
use App\Models\Task\TaskArtifactFilter;
use App\Services\JsonSchema\JsonSchemaService;

class ArtifactFilterService
{
    private ?Artifact $artifact           = null;
    private bool      $includePageNumbers = false;
    private bool      $includeText        = false;
    private bool      $includeFiles       = false;
    private bool      $includeJson        = false;
    private array     $fragmentSelector   = [];

    public function setFilter(TaskArtifactFilter $artifactFilter): static
    {
        $this->includeFiles     = $artifactFilter->include_files;
        $this->includeJson      = $artifactFilter->include_json;
        $this->includeText      = $artifactFilter->include_text;
        $this->fragmentSelector = $artifactFilter->fragment_selector;

        return $this;
    }

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

    public function hasFiles(): bool
    {
        return $this->includeFiles && $this->artifact->storedFiles->isNotEmpty();
    }

    public function hasJson(): bool
    {
        return $this->includeJson && $this->artifact->json_content;
    }

    public function hasText(): bool
    {
        return $this->includeText && $this->artifact->text_content;
    }

    public function isTextOnly(): bool
    {
        return $this->includeText && !$this->hasFiles() && !$this->hasJson();
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

            if ($this->hasText()) {
                $data['text_content'] = $this->getTextContent();
            }

            if ($this->hasFiles()) {
                $data['files'] = $this->artifact->storedFiles;
            }

            if ($this->hasJson()) {
                $data['json_content'] = $this->getFilteredData();
            }

            return $data;
        }
    }
}
