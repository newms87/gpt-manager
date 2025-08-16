<?php

namespace App\Services\ContentSearch;

use App\Models\Prompt\PromptDirective;
use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Services\ContentSearch\Exceptions\InvalidSearchParametersException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class ContentSearchRequest
{
    private ?string         $naturalLanguageQuery = null;
    private ?string         $fieldPath            = null;
    private ?string         $regexPattern         = null;
    private                 $validationCallback   = null;
    private ?string         $llmModel             = null;
    private ?TaskDefinition $taskDefinition       = null;
    private                 $artifacts            = null;  // Can be Collection or SupportCollection
    private                 $directives           = null;  // Can be Collection or SupportCollection
    private bool            $requireValidation    = false;
    private int             $maxAttempts          = 3;
    private array           $searchOptions        = [];

    public static function create(): self
    {
        return new self();
    }

    /**
     * Set natural language query for LLM-based extraction
     */
    public function withNaturalLanguageQuery(string $query): self
    {
        $this->naturalLanguageQuery = $query;

        return $this;
    }

    /**
     * Set field path for direct field extraction (e.g., 'template_stored_file_id')
     */
    public function withFieldPath(string $fieldPath): self
    {
        $this->fieldPath = $fieldPath;

        return $this;
    }

    /**
     * Set regex pattern for text pattern matching
     */
    public function withRegexPattern(string $pattern): self
    {
        $this->regexPattern = $pattern;

        return $this;
    }

    /**
     * Set validation callback for extracted values
     */
    public function withValidation(callable $callback, bool $required = true): self
    {
        $this->validationCallback = $callback;
        $this->requireValidation  = $required;

        return $this;
    }

    /**
     * Set LLM model for text extraction
     */
    public function withLlmModel(string $model): self
    {
        $this->llmModel = $model;

        return $this;
    }

    /**
     * Set task definition for team context and configuration
     */
    public function withTaskDefinition(TaskDefinition $taskDefinition): self
    {
        $this->taskDefinition = $taskDefinition;

        $this->directives = [];
        foreach($taskDefinition->taskDefinitionDirectives as $taskDefinitionDirective) {
            $this->directives[] = $taskDefinitionDirective->directive;
        }

        return $this;
    }

    /**
     * Set artifacts to search through
     * @param Collection|SupportCollection $artifacts
     */
    public function searchArtifacts($artifacts): self
    {
        $this->artifacts = $artifacts;

        return $this;
    }

    /**
     * Set directives to search through
     * @param Collection|SupportCollection $directives
     */
    public function searchDirectives($directives): self
    {
        $this->directives = $directives;

        return $this;
    }

    /**
     * Set maximum retry attempts for extraction
     */
    public function withMaxAttempts(int $attempts): self
    {
        if ($attempts < 1) {
            throw new InvalidSearchParametersException('maxAttempts', 'Must be at least 1');
        }

        $this->maxAttempts = $attempts;

        return $this;
    }

    /**
     * Set additional search options
     */
    public function withOptions(array $options): self
    {
        $this->searchOptions = array_merge($this->searchOptions, $options);

        return $this;
    }

    /**
     * Set specific search option
     */
    public function withOption(string $key, mixed $value): self
    {
        $this->searchOptions[$key] = $value;

        return $this;
    }

    // Getters
    public function getNaturalLanguageQuery(): ?string
    {
        return $this->naturalLanguageQuery;
    }

    public function getFieldPath(): ?string
    {
        return $this->fieldPath;
    }

    public function getRegexPattern(): ?string
    {
        return $this->regexPattern;
    }

    public function getValidationCallback(): ?callable
    {
        return $this->validationCallback;
    }

    public function getLlmModel(): ?string
    {
        return $this->llmModel ?: config('google-docs.file_id_detection_model');
    }

    public function getTaskDefinition(): ?TaskDefinition
    {
        return $this->taskDefinition;
    }

    /**
     * @return Artifact[]|Collection|SupportCollection|null
     */
    public function getArtifacts()
    {
        return $this->artifacts;
    }

    /**
     * @return PromptDirective[]|Collection|SupportCollection|null
     */
    public function getDirectives()
    {
        return $this->directives;
    }

    public function isValidationRequired(): bool
    {
        return $this->requireValidation;
    }

    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function getSearchOptions(): array
    {
        return $this->searchOptions;
    }

    public function getOption(string $key, mixed $default = null): mixed
    {
        return $this->searchOptions[$key] ?? $default;
    }

    /**
     * Validate the request configuration
     */
    public function validate(): void
    {
        // Must have at least one search method
        if (!$this->naturalLanguageQuery && !$this->fieldPath && !$this->regexPattern) {
            throw new InvalidSearchParametersException(
                'searchMethod',
                'Must specify at least one search method: naturalLanguageQuery, fieldPath, or regexPattern'
            );
        }

        // Validate regex pattern if provided
        if ($this->regexPattern) {
            if (@preg_match($this->regexPattern, '') === false) {
                throw new InvalidSearchParametersException(
                    'regexPattern',
                    'Invalid regex pattern: ' . preg_last_error_msg()
                );
            }
        }
    }

    /**
     * Get team ID from task definition
     */
    public function getTeamId(): ?int
    {
        return $this->taskDefinition?->team_id;
    }

    /**
     * Check if request uses LLM extraction
     */
    public function usesLlmExtraction(): bool
    {
        return !empty($this->naturalLanguageQuery);
    }

    /**
     * Check if request uses field path extraction
     */
    public function usesFieldPath(): bool
    {
        return !empty($this->fieldPath);
    }

    /**
     * Check if request uses regex pattern extraction
     */
    public function usesRegexPattern(): bool
    {
        return !empty($this->regexPattern);
    }
}
