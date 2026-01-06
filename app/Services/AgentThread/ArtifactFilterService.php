<?php

namespace App\Services\AgentThread;

use App\Models\Task\Artifact;
use App\Models\Task\TaskArtifactFilter;
use App\Services\JsonSchema\JsonSchemaService;
use App\Services\TextTranscodeHelper;

class ArtifactFilterService
{
    private ?Artifact $artifact             = null;

    private bool $includeText               = true;

    private bool $includeFiles              = true;

    private bool $includeJson               = true;

    private bool $includeMeta               = true;

    private bool $includeTextTranscodes     = true;

    private array $jsonFragmentSelector = [];

    private array $metaFragmentSelector = [];

    public function setFilter(TaskArtifactFilter $artifactFilter): static
    {
        $this->includeFiles = $artifactFilter->include_files;
        $this->includeJson  = $artifactFilter->include_json;
        $this->includeText  = $artifactFilter->include_text;
        $this->includeMeta  = $artifactFilter->include_meta;

        $this->jsonFragmentSelector = $artifactFilter->schemaFragment?->fragment_selector ?? [];
        $this->metaFragmentSelector = (array)($artifactFilter->meta_fragment_selector ?: []);

        return $this;
    }

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

    public function includeJson(bool $included = true, array $fragmentSelector = []): static
    {
        $this->includeJson          = $included;
        $this->jsonFragmentSelector = $fragmentSelector;

        return $this;
    }

    public function includeMeta(bool $included = true, array $fragmentSelector = []): static
    {
        $this->includeMeta          = $included;
        $this->metaFragmentSelector = $fragmentSelector;

        return $this;
    }

    public function includeTextTranscodes(bool $included = true): static
    {
        $this->includeTextTranscodes = $included;

        return $this;
    }

    public function hasText(): bool
    {
        return $this->includeText && $this->artifact->text_content;
    }

    public function hasFiles(): bool
    {
        return $this->includeFiles && $this->artifact->storedFiles->isNotEmpty();
    }

    public function hasJson(): bool
    {
        return $this->includeJson && $this->artifact->json_content;
    }

    public function hasMeta(): bool
    {
        return $this->includeMeta && $this->artifact->meta;
    }

    public function hasTextTranscodes(): bool
    {
        return $this->includeTextTranscodes && $this->artifact->storedFiles->isNotEmpty();
    }

    public function isTextOnly(): bool
    {
        // Text-only when we ONLY have text content and/or text transcodes (no files, json, meta)
        return !$this->hasFiles() && !$this->hasJson() && !$this->hasMeta();
    }

    /**
     * Determines if after applying the filter to the artifact, if the artifact will be empty
     */
    public function willBeEmpty(): bool
    {
        if (!$this->artifact) {
            return true;
        }

        if ($this->hasText() || $this->hasFiles()) {
            return false;
        }

        if ($this->hasTextTranscodes()) {
            return false;
        }

        if ($this->hasJson() && $this->getFilteredJson()) {
            return false;
        }

        if ($this->hasMeta() && $this->getFilteredMeta()) {
            return false;
        }

        return true;
    }

    public function getTextContent(): ?string
    {
        return $this->artifact->text_content;
    }

    public function getFilteredJson(): ?array
    {
        if ($this->jsonFragmentSelector) {
            return app(JsonSchemaService::class)->useId()->filterDataByFragmentSelector($this->artifact->json_content ?? [], $this->jsonFragmentSelector);
        }

        return $this->artifact->json_content;
    }

    public function getFilteredMeta(): ?array
    {
        if ($this->metaFragmentSelector) {
            return app(JsonSchemaService::class)->filterDataByFragmentSelector($this->artifact->meta ?? [], $this->metaFragmentSelector);
        }

        return $this->artifact->meta;
    }

    /**
     * Create a new artifact with the filtered content
     */
    public function toFilteredArtifact(): ?Artifact
    {
        if ($this->willBeEmpty()) {
            return null;
        }

        // Use copy() method which handles stored files relationship
        $filteredArtifact = $this->artifact->copy();

        if (!$this->hasText()) {
            $filteredArtifact->text_content = null;
        }

        if ($this->hasJson()) {
            $filteredArtifact->json_content = $this->getFilteredJson();
        } else {
            $filteredArtifact->json_content = null;
        }

        if ($this->hasMeta()) {
            $filteredArtifact->meta = $this->getFilteredMeta();
        } else {
            $filteredArtifact->meta = null;
        }

        $filteredArtifact->save();

        return $filteredArtifact;
    }

    protected function buildTextTranscodes(): ?string
    {
        $allTranscodes = [];

        foreach ($this->artifact->storedFiles as $storedFile) {
            $transcodeArray   = $storedFile->getTextTranscodesContent();
            $transcodeContent = TextTranscodeHelper::formatTextTranscodes($transcodeArray);
            if ($transcodeContent) {
                $allTranscodes[] = "=== File: {$storedFile->filename} ===\n" . $transcodeContent;
            }
        }

        return $allTranscodes ? implode("\n\n", $allTranscodes) : null;
    }

    public function filter(): array|string|null
    {
        if ($this->willBeEmpty()) {
            return null;
        }

        if ($this->isTextOnly()) {
            $parts = [];

            if ($this->hasText()) {
                $parts[] = $this->getTextContent();
            }

            if ($this->hasTextTranscodes()) {
                $transcodes = $this->buildTextTranscodes();
                if ($transcodes) {
                    $parts[] = $transcodes;
                }
            }

            return $parts ? implode("\n\n", $parts) : null;
        } else {
            $data = [];

            if ($this->hasText()) {
                $data['text_content'] = $this->getTextContent();
            }

            if ($this->hasFiles()) {
                $data['files'] = $this->artifact->storedFiles;
            }

            // Text transcodes are available independently of files
            // (e.g., for identity extraction we want text transcodes but not images)
            if ($this->hasTextTranscodes()) {
                $transcodes = $this->buildTextTranscodes();
                if ($transcodes) {
                    $data['text_transcodes'] = $transcodes;
                }
            }

            if ($this->hasJson()) {
                $data['json_content'] = $this->getFilteredJson();
            }

            if ($this->hasMeta()) {
                $data['meta'] = $this->getFilteredMeta();
            }

            return $data;
        }
    }
}
