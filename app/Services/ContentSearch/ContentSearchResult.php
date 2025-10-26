<?php

namespace App\Services\ContentSearch;

use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinitionDirective;

class ContentSearchResult
{
    private ?string $extractedValue = null;

    private bool $found = false;

    private string $extractionMethod = '';

    private ?Artifact $sourceArtifact = null;

    private $sourceDirective = null;

    private string $sourceType = '';

    private string $sourceLocation = '';

    private array $metadata = [];

    private bool $validated = false;

    private ?string $validationError = null;

    private int $attempts = 0;

    private float $confidenceScore = 0.0;

    private array $allMatches = [];

    private array $debugInfo = [];

    public function __construct()
    {
        //
    }

    public static function create(): self
    {
        return new self();
    }

    /**
     * Mark result as found with extracted value
     */
    public function setFound(string $value, string $method = '', float $confidence = 1.0): self
    {
        $this->extractedValue   = $value;
        $this->found            = true;
        $this->extractionMethod = $method;
        $this->confidenceScore  = $confidence;

        return $this;
    }

    /**
     * Mark result as not found
     */
    public function setNotFound(string $reason = ''): self
    {
        $this->extractedValue = null;
        $this->found          = false;
        if ($reason) {
            $this->addDebugInfo('not_found_reason', $reason);
        }

        return $this;
    }

    /**
     * Set the source artifact that contained the value
     */
    public function setSourceArtifact(Artifact $artifact, string $location = ''): self
    {
        $this->sourceArtifact = $artifact;
        $this->sourceType     = 'artifact';
        $this->sourceLocation = $location;

        return $this;
    }

    /**
     * Set the source directive that contained the value
     */
    public function setSourceDirective($directive, string $location = ''): self
    {
        $this->sourceDirective = $directive;
        $this->sourceType      = 'directive';
        $this->sourceLocation  = $location;

        return $this;
    }

    /**
     * Set validation status
     */
    public function setValidated(bool $valid, ?string $error = null): self
    {
        $this->validated       = $valid;
        $this->validationError = $error;

        return $this;
    }

    /**
     * Increment attempt counter
     */
    public function incrementAttempts(): self
    {
        $this->attempts++;

        return $this;
    }

    /**
     * Set metadata for the extraction
     */
    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * Add single metadata item
     */
    public function addMetadata(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;

        return $this;
    }

    /**
     * Set all matches found during extraction
     */
    public function setAllMatches(array $matches): self
    {
        $this->allMatches = $matches;

        return $this;
    }

    /**
     * Add debug information
     */
    public function addDebugInfo(string $key, mixed $value): self
    {
        $this->debugInfo[$key] = $value;

        return $this;
    }

    /**
     * Set multiple debug info items
     */
    public function setDebugInfo(array $debugInfo): self
    {
        $this->debugInfo = array_merge($this->debugInfo, $debugInfo);

        return $this;
    }

    // Getters
    public function getValue(): ?string
    {
        return $this->extractedValue;
    }

    public function isFound(): bool
    {
        return $this->found;
    }

    public function getExtractionMethod(): string
    {
        return $this->extractionMethod;
    }

    public function getSourceArtifact(): ?Artifact
    {
        return $this->sourceArtifact;
    }

    public function getSourceDirective()
    {
        return $this->sourceDirective;
    }

    public function getSourceType(): string
    {
        return $this->sourceType;
    }

    public function getSourceLocation(): string
    {
        return $this->sourceLocation;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getMetadataItem(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    public function isValidated(): bool
    {
        return $this->validated;
    }

    public function getValidationError(): ?string
    {
        return $this->validationError;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function getConfidenceScore(): float
    {
        return $this->confidenceScore;
    }

    public function getAllMatches(): array
    {
        return $this->allMatches;
    }

    public function getDebugInfo(): array
    {
        return $this->debugInfo;
    }

    public function getDebugItem(string $key, mixed $default = null): mixed
    {
        return $this->debugInfo[$key] ?? $default;
    }

    /**
     * Check if extraction was successful and validated
     */
    public function isSuccessful(): bool
    {
        return $this->found && $this->validated;
    }

    /**
     * Check if validation failed
     */
    public function hasValidationError(): bool
    {
        return !empty($this->validationError);
    }

    /**
     * Get source identifier for logging
     */
    public function getSourceIdentifier(): string
    {
        if ($this->sourceArtifact) {
            return "artifact:{$this->sourceArtifact->id}";
        }

        if ($this->sourceDirective) {
            $id = is_object($this->sourceDirective) && property_exists($this->sourceDirective, 'id')
                ? $this->sourceDirective->id
                : 'unknown';

            return "directive:{$id}";
        }

        return 'unknown';
    }

    /**
     * Get human-readable source description
     */
    public function getSourceDescription(): string
    {
        $description = $this->sourceType;

        if ($this->sourceArtifact) {
            $description .= " (ID: {$this->sourceArtifact->id})";
            if ($this->sourceArtifact->name) {
                $description .= " '{$this->sourceArtifact->name}'";
            }
        } elseif ($this->sourceDirective) {
            $id = is_object($this->sourceDirective) && property_exists($this->sourceDirective, 'id')
                ? $this->sourceDirective->id
                : 'unknown';
            $description .= " (ID: {$id})";

            if (is_object($this->sourceDirective) && property_exists($this->sourceDirective, 'directive') && $this->sourceDirective->directive) {
                $name = is_object($this->sourceDirective->directive) && property_exists($this->sourceDirective->directive, 'name')
                    ? $this->sourceDirective->directive->name
                    : 'Unknown';
                $description .= " '{$name}'";
            }
        }

        if ($this->sourceLocation) {
            $description .= " at {$this->sourceLocation}";
        }

        return $description;
    }

    /**
     * Convert to array for logging/debugging
     */
    public function toArray(): array
    {
        return [
            'found'             => $this->found,
            'value'             => $this->extractedValue,
            'extraction_method' => $this->extractionMethod,
            'source_type'       => $this->sourceType,
            'source_identifier' => $this->getSourceIdentifier(),
            'source_location'   => $this->sourceLocation,
            'validated'         => $this->validated,
            'validation_error'  => $this->validationError,
            'attempts'          => $this->attempts,
            'confidence_score'  => $this->confidenceScore,
            'metadata'          => $this->metadata,
            'all_matches_count' => count($this->allMatches),
            'debug_info'        => $this->debugInfo,
        ];
    }

    /**
     * Create a result for field path extraction success
     */
    public static function fieldPathFound(string $value, Artifact $artifact, string $fieldPath): self
    {
        return self::create()
            ->setFound($value, 'field_path', 1.0)
            ->setSourceArtifact($artifact, $fieldPath)
            ->setValidated(true);
    }

    /**
     * Create a result for regex extraction success
     */
    public static function regexFound(string $value, $source, string $pattern, array $allMatches = []): self
    {
        $result = self::create()
            ->setFound($value, 'regex', 1.0)
            ->setAllMatches($allMatches)
            ->addMetadata('regex_pattern', $pattern)
            ->setValidated(true);

        if ($source instanceof Artifact) {
            $result->setSourceArtifact($source, 'text_content');
        } elseif ($source instanceof TaskDefinitionDirective || (is_object($source) && property_exists($source, 'directive_text'))) {
            $result->setSourceDirective($source, 'directive_text');
        }

        return $result;
    }

    /**
     * Create a result for LLM extraction success
     */
    public static function llmFound(string $value, $source, string $model, float $confidence = 0.8): self
    {
        $result = self::create()
            ->setFound($value, 'llm', $confidence)
            ->addMetadata('llm_model', $model)
            ->setValidated(true);

        if ($source instanceof Artifact) {
            $result->setSourceArtifact($source, 'text_content');
        } elseif ($source instanceof TaskDefinitionDirective || (is_object($source) && property_exists($source, 'directive_text'))) {
            $result->setSourceDirective($source, 'directive_text');
        }

        return $result;
    }

    /**
     * Create a not found result
     */
    public static function notFound(string $reason = ''): self
    {
        return self::create()->setNotFound($reason);
    }
}
