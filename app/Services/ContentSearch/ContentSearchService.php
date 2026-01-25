<?php

namespace App\Services\ContentSearch;

use App\Models\Agent\Agent;
use App\Models\Task\Artifact;
use App\Repositories\ContentSearch\ContentSearchRepository;
use App\Services\AgentThread\AgentThreadBuilderService;
use App\Services\ContentSearch\Exceptions\InvalidSearchParametersException;
use Newms87\Danx\Traits\HasDebugLogging;
use Exception;
use Illuminate\Support\Arr;

class ContentSearchService
{
    use HasDebugLogging;

    /**
     * Main search method - handles all search types based on request configuration
     */
    public function search(ContentSearchRequest $request): ContentSearchResult
    {
        static::logDebug('Starting content search', [
            'uses_llm'        => $request->usesLlmExtraction(),
            'uses_field_path' => $request->usesFieldPath(),
            'uses_regex'      => $request->usesRegexPattern(),
            'team_id'         => $request->getTeamId(),
        ]);

        // Validate request configuration
        $request->validate();

        // Validate team access
        if (!$this->validateTeamAccess($request)) {
            throw new InvalidSearchParametersException(
                'teamAccess',
                'Invalid team access for provided resources'
            );
        }

        return $this->performSearch($request);
    }

    /**
     * Search for a field value in artifacts using multiple strategies
     */
    public function searchArtifacts(ContentSearchRequest $request): ContentSearchResult
    {
        $artifacts = $request->getArtifacts();

        // Handle null or empty artifacts
        if (!$artifacts || $artifacts->isEmpty()) {
            static::logDebug('No artifacts to search');

            return ContentSearchResult::notFound('No artifacts provided for searching');
        }

        static::logDebug('Searching artifacts', [
            'artifact_count' => $artifacts->count(),
        ]);

        if ($request->usesFieldPath()) {
            foreach ($artifacts as $artifact) {
                $result = $this->findInFieldPath($request->getFieldPath(), $artifact);
                if ($result->isFound()) {
                    static::logDebug('Found via field path', [
                        'value'     => $result->getValue(),
                        'source'    => $result->getSourceIdentifier(),
                        'validated' => $result->isValidated(),
                    ]);

                    return $result;
                }
            }
        }

        if ($request->usesLlmExtraction()) {
            $result = $this->searchArtifactsWithLlm($request);

            if ($result->isFound()) {
                return $result;
            }
        }

        static::logDebug('No content found in artifacts');

        return ContentSearchResult::notFound('No matching content found in artifacts');
    }

    /**
     * Search artifacts by field path in meta or json_content
     */
    private function findInFieldPath(string $fieldPath, Artifact $artifact): ContentSearchResult
    {
        $result = Arr::get($artifact->json_content ?: [], $fieldPath) ?: Arr::get($artifact->meta ?: [], $fieldPath);

        if ($result) {
            return ContentSearchResult::fieldPathFound($result, $artifact, "json_content.{$fieldPath}");
        }

        return ContentSearchResult::notFound("Field path '{$fieldPath}' not found");
    }

    /**
     * Filter out potential artifacts
     */
    private function getPotentialArtifacts(ContentSearchRequest $request)
    {
        $artifacts = $request->getArtifacts();

        if (!$artifacts) {
            return collect();
        }

        $artifacts = $artifacts->filter(fn($artifact) => !empty($artifact->text_content));

        if ($request->usesRegexPattern()) {
            $pattern   = $request->getRegexPattern();
            $artifacts = $artifacts->filter(fn($artifact) => preg_match($pattern, $artifact->text_content));
        }

        return $artifacts->sortBy(fn($artifact) => strlen($artifact->text_content));
    }

    /**
     * Search artifacts using LLM-based text extraction
     */
    private function searchArtifactsWithLlm(ContentSearchRequest $request): ContentSearchResult
    {
        $potentialArtifacts = $this->getPotentialArtifacts($request);

        static::logDebug('Starting LLM search on artifacts', [
            'query'               => $request->getNaturalLanguageQuery(),
            'potential_artifacts' => $potentialArtifacts->count(),
            'model'               => $request->getLlmModel() ?: 'default',
        ]);

        foreach ($potentialArtifacts as $artifact) {
            try {
                $result = $this->extractWithLlm($artifact->text_content, $request, $artifact);
                if ($result->isFound()) {
                    return $result;
                }
            } catch (Exception $e) {
                static::logDebug('LLM extraction failed for artifact', [
                    'artifact_id' => $artifact->id,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        static::logDebug('LLM search found no matches in artifacts');

        return ContentSearchResult::notFound('LLM extraction found no matches');
    }

    /**
     * Search directives using LLM (searches all directive text at once)
     */
    private function searchDirectivesWithLlm(ContentSearchRequest $request): ContentSearchResult
    {
        $directives = $request->getDirectives();

        if (!$directives || (is_countable($directives) && count($directives) === 0)) {
            return ContentSearchResult::notFound('No directives to search');
        }

        $directiveText = '';
        foreach ($directives as $directive) {
            // Handle both stdClass and Model instances
            if (is_object($directive)) {
                if (property_exists($directive, 'directive_text')) {
                    $directiveText .= $directive->directive_text . ' ';
                } elseif (property_exists($directive, 'directive') && is_object($directive->directive)) {
                    if (property_exists($directive->directive, 'directive_text')) {
                        $directiveText .= $directive->directive->directive_text . ' ';
                    }
                }
            }
        }

        if (!$directiveText) {
            return ContentSearchResult::notFound('No directives with text content');
        }

        try {
            $result = $this->extractWithLlm(trim($directiveText), $request);
            if ($result->isFound()) {
                // Try to identify which directive contained the result
                $sourceDirective = $this->identifySourceDirective($result->getValue(), $directives);
                if ($sourceDirective) {
                    $result->setSourceDirective($sourceDirective, 'directive_text');
                }

                static::logDebug('LLM extraction successful from directives', [
                    'extracted_value'     => $result->getValue(),
                    'source_directive_id' => $sourceDirective ? (property_exists($sourceDirective, 'id') ? $sourceDirective->id : null) : null,
                ]);

                return $result;
            }
        } catch (Exception $e) {
            static::logDebug('LLM extraction failed for directives', [
                'error' => $e->getMessage(),
            ]);

            // Don't throw, just return not found for unit tests
            return ContentSearchResult::notFound('LLM extraction failed: ' . $e->getMessage());
        }

        return ContentSearchResult::notFound('LLM extraction found no matches in directives');
    }

    /**
     * Extract content using LLM agent
     */
    private function extractWithLlm(string $textContent, ContentSearchRequest $request, ?Artifact $sourceArtifact = null): ContentSearchResult
    {
        $query = $request->getNaturalLanguageQuery();
        $model = $request->getLlmModel();

        static::logDebug('Starting LLM extraction', [
            'text_length' => strlen($textContent),
            'query'       => $query,
            'model'       => $model,
        ]);

        // Create detection agent
        $agent = Agent::firstOrCreate([
            'team_id' => null,
            'name'    => 'Content Search Agent: ' . $model,
        ], ['model' => $model]);

        $instructions = $this->buildLlmInstructions($query, $textContent);

        // Build and run the agent thread
        $threadRun = AgentThreadBuilderService::for($agent)
            ->named('Content Search: ' . substr($query, 0, 30))
            ->withMessage($instructions)
            ->run();

        $textContent = $threadRun->lastMessage?->getCleanContent();

        if (!$textContent) {
            static::logDebug('LLM agent failed to provide response');

            return ContentSearchResult::notFound('LLM agent failed to respond');
        }

        // Handle "NONE" response
        if (strtoupper($textContent) === 'NONE') {
            static::logDebug('LLM found no content');

            return ContentSearchResult::notFound('LLM found no matching content');
        }

        static::logDebug('LLM agent response received', [
            'response'        => $textContent,
            'response_length' => strlen($textContent),
        ]);

        $result = ContentSearchResult::llmFound($textContent, $sourceArtifact, $model);
        $result->addMetadata('llm_instructions', $instructions);

        static::logDebug('LLM extraction found a result matching the query');

        return $result;
    }

    /**
     * Build LLM instructions for content extraction
     */
    private function buildLlmInstructions(string $query, string $textContent): string
    {
        return <<<INSTRUCTIONS
You need to extract specific content from the following text based on this query:

Query: {$query}

Text Content:
{$textContent}

Instructions:
1. Carefully read through the text content
2. Look for content that matches the query requirements
3. Extract the most relevant match
4. If you find matching content, respond with ONLY that content and nothing else
5. If no matching content is found, respond with "NONE"

Important:
- Return only the extracted content, no explanations
- Be precise and accurate
- If multiple matches exist, return the most relevant one
- Do not add quotes, formatting, or additional text

Response:
- If found, respond with ONLY the specified value defined in the Query
- If not found, response with "NONE" (no other text!)
INSTRUCTIONS;
    }

    /**
     * Validate extracted result using validation callback
     */
    private function validateResult(ContentSearchResult $result, ContentSearchRequest $request): bool
    {
        if (!$result->isFound()) {
            return false;
        }

        $validationCallback = $request->getValidationCallback();
        if (!$validationCallback) {
            $result->setValidated(true);

            return true;
        }

        try {
            $isValid = $validationCallback($result->getValue());
            $result->setValidated($isValid);

            if (!$isValid && $request->isValidationRequired()) {
                static::logDebug('Validation failed for extracted value', [
                    'value'  => $result->getValue(),
                    'source' => $result->getSourceIdentifier(),
                ]);

                return false;
            }

            return true;
        } catch (Exception $e) {
            $result->setValidated(false, $e->getMessage());

            if ($request->isValidationRequired()) {
                static::logDebug('Validation error', [
                    'value' => $result->getValue(),
                    'error' => $e->getMessage(),
                ]);

                return false;
            }

            return true;
        }
    }

    /**
     * Try to identify which directive contained the extracted value
     */
    private function identifySourceDirective(string $extractedValue, $directives)
    {
        if (!$directives) {
            return null;
        }

        foreach ($directives as $directive) {
            $directiveText = null;

            // Handle different directive structures
            if (is_object($directive)) {
                if (property_exists($directive, 'directive_text')) {
                    $directiveText = $directive->directive_text;
                } elseif (property_exists($directive, 'directive') && is_object($directive->directive)) {
                    if (property_exists($directive->directive, 'directive_text')) {
                        $directiveText = $directive->directive->directive_text;
                    }
                }
            }

            if ($directiveText && stripos($directiveText, $extractedValue) !== false) {
                return $directive;
            }
        }

        return null;
    }

    /**
     * Perform the search based on request configuration
     */
    private function performSearch(ContentSearchRequest $request): ContentSearchResult
    {
        // Search in artifacts if provided
        if ($request->getArtifacts() !== null) {
            $result = $this->searchArtifacts($request);

            if ($result->isFound()) {
                $this->validateResult($result, $request);

                static::logDebug('Search completed with artifact result', [
                    'found'             => $result->isFound(),
                    'value'             => $result->getValue(),
                    'source'            => $result->getSourceIdentifier(),
                    'extraction_method' => $result->getExtractionMethod(),
                ]);

                return $result;
            }
        }

        // Fall back to searching directives if artifacts not found or not provided
        if ($request->usesLlmExtraction() && $request->getDirectives()) {
            $result = $this->searchDirectivesWithLlm($request);

            if ($result->isFound()) {
                $this->validateResult($result, $request);
            }
        } else {
            $result = ContentSearchResult::notFound('No content found in artifacts or directives');
        }

        static::logDebug('Search completed', [
            'found'             => $result->isFound(),
            'value'             => $result->getValue(),
            'source'            => $result->getSourceIdentifier(),
            'extraction_method' => $result->getExtractionMethod(),
        ]);

        return $result;
    }

    /**
     * Validate team access for the request
     */
    private function validateTeamAccess(ContentSearchRequest $request): bool
    {
        $teamId = $request->getTeamId();
        if (!$teamId) {
            return false;
        }

        return app(ContentSearchRepository::class)->validateTeamAccess(
            $teamId,
            $request->getArtifacts(),
            $request->getTaskDefinition()
        );
    }
}
