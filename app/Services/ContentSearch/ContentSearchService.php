<?php

namespace App\Services\ContentSearch;

use App\Models\Agent\Agent;
use App\Models\Agent\AgentThread;
use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskDefinitionDirective;
use App\Repositories\ContentSearch\ContentSearchRepository;
use App\Repositories\ThreadRepository;
use App\Services\AgentThread\AgentThreadService;
use App\Services\ContentSearch\Exceptions\ContentExtractionException;
use App\Services\ContentSearch\Exceptions\InvalidSearchParametersException;
use App\Services\ContentSearch\Exceptions\NoContentFoundException;
use App\Services\ContentSearch\Exceptions\ValidationFailedException;
use App\Traits\HasDebugLogging;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

class ContentSearchService
{
    use HasDebugLogging;

    private ContentSearchRepository $repository;

    public function __construct(ContentSearchRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Main search method - handles all search types based on request configuration
     */
    public function search(ContentSearchRequest $request): ContentSearchResult
    {
        static::log('ContentSearchService: Starting content search', [
            'search_target' => $request->getSearchTarget(),
            'uses_llm' => $request->usesLlmExtraction(),
            'uses_field_path' => $request->usesFieldPath(),
            'uses_regex' => $request->usesRegexPattern(),
            'team_id' => $request->getTeamId(),
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

        return DB::transaction(function () use ($request) {
            return $this->performSearch($request);
        });
    }

    /**
     * Search for a field value in artifacts using multiple strategies
     */
    public function searchArtifacts(ContentSearchRequest $request): ContentSearchResult
    {
        static::log('ContentSearchService: Searching artifacts', [
            'artifact_count' => $request->getArtifacts()->count(),
            'search_methods' => $this->getActiveSearchMethods($request),
        ]);

        // Strategy 1: Direct field path extraction
        if ($request->usesFieldPath()) {
            $result = $this->searchArtifactsByFieldPath($request);
            if ($result->isFound()) {
                $this->validateResult($result, $request);
                static::log('ContentSearchService: Found via field path', [
                    'value' => $result->getValue(),
                    'source' => $result->getSourceIdentifier(),
                    'validated' => $result->isValidated(),
                ]);
                return $result;
            }
        }

        // Strategy 2: Regex pattern matching
        if ($request->usesRegexPattern()) {
            $result = $this->searchArtifactsByRegex($request);
            if ($result->isFound()) {
                $this->validateResult($result, $request);
                static::log('ContentSearchService: Found via regex', [
                    'value' => $result->getValue(),
                    'source' => $result->getSourceIdentifier(),
                    'pattern' => $request->getRegexPattern(),
                    'validated' => $result->isValidated(),
                ]);
                return $result;
            }
        }

        // Strategy 3: LLM-based extraction
        if ($request->usesLlmExtraction()) {
            $result = $this->searchArtifactsWithLlm($request);
            if ($result->isFound()) {
                $this->validateResult($result, $request);
                static::log('ContentSearchService: Found via LLM', [
                    'value' => $result->getValue(),
                    'source' => $result->getSourceIdentifier(),
                    'confidence' => $result->getConfidenceScore(),
                    'validated' => $result->isValidated(),
                ]);
                return $result;
            }
        }

        static::log('ContentSearchService: No content found in artifacts');
        return ContentSearchResult::notFound('No matching content found in artifacts');
    }

    /**
     * Search for content in task definition directives
     */
    public function searchDirectives(ContentSearchRequest $request): ContentSearchResult
    {
        static::log('ContentSearchService: Searching directives', [
            'directive_count' => $request->getDirectives()->count(),
            'search_methods' => $this->getActiveSearchMethods($request),
        ]);

        $directives = $request->getDirectives();

        // When using natural language queries, search all directives at once
        if ($request->usesLlmExtraction()) {
            $result = $this->searchDirectivesWithLlm($request);
            if ($result->isFound() && $this->validateResult($result, $request)) {
                static::log('ContentSearchService: Found via LLM in directives', [
                    'value' => $result->getValue(),
                    'source' => $result->getSourceIdentifier(),
                ]);
                return $result;
            }
        }

        // For regex/field path, search individual directives
        foreach ($directives as $directive) {
            if (empty($directive->directive_text)) {
                continue;
            }

            static::log('ContentSearchService: Checking directive', [
                'directive_id' => $directive->id,
                'directive_name' => $directive->directive->name ?? 'Unknown',
                'text_length' => strlen($directive->directive_text),
            ]);

            if ($request->usesRegexPattern()) {
                $result = $this->searchDirectiveByRegex($directive, $request);
                if ($result->isFound() && $this->validateResult($result, $request)) {
                    static::log('ContentSearchService: Found via regex in directive', [
                        'value' => $result->getValue(),
                        'directive_id' => $directive->id,
                    ]);
                    return $result;
                }
            }
        }

        static::log('ContentSearchService: No content found in directives');
        return ContentSearchResult::notFound('No matching content found in directives');
    }

    /**
     * Search artifacts by field path in meta or json_content
     */
    private function searchArtifactsByFieldPath(ContentSearchRequest $request): ContentSearchResult
    {
        $fieldPath = $request->getFieldPath();
        $artifacts = $request->getArtifacts();

        static::log('ContentSearchService: Starting field path search', [
            'field_path' => $fieldPath,
            'artifact_count' => $artifacts->count(),
        ]);

        foreach ($artifacts as $artifact) {
            static::log('ContentSearchService: Checking artifact for field path', [
                'artifact_id' => $artifact->id,
                'has_json_content' => !empty($artifact->json_content),
                'has_meta' => !empty($artifact->meta),
            ]);

            // Check in json_content first
            if ($artifact->json_content) {
                $value = Arr::get($artifact->json_content, $fieldPath);
                if ($value) {
                    static::log('ContentSearchService: Field path found in json_content', [
                        'artifact_id' => $artifact->id,
                        'field_path' => $fieldPath,
                        'value' => $value,
                    ]);
                    return ContentSearchResult::fieldPathFound($value, $artifact, "json_content.{$fieldPath}");
                }
            }

            // Check in meta
            if ($artifact->meta) {
                $value = Arr::get($artifact->meta, $fieldPath);
                if ($value) {
                    static::log('ContentSearchService: Field path found in meta', [
                        'artifact_id' => $artifact->id,
                        'field_path' => $fieldPath,
                        'value' => $value,
                    ]);
                    return ContentSearchResult::fieldPathFound($value, $artifact, "meta.{$fieldPath}");
                }
            }
        }

        static::log('ContentSearchService: Field path not found in any artifact');
        return ContentSearchResult::notFound("Field path '{$fieldPath}' not found");
    }

    /**
     * Search artifacts by regex pattern in text content
     */
    private function searchArtifactsByRegex(ContentSearchRequest $request): ContentSearchResult
    {
        $pattern = $request->getRegexPattern();
        $artifacts = $request->getArtifacts()->filter(function ($artifact) {
            return !empty($artifact->text_content);
        });

        static::log('ContentSearchService: Starting regex search', [
            'pattern' => $pattern,
            'artifacts_with_text' => $artifacts->count(),
        ]);

        foreach ($artifacts as $artifact) {
            $textContent = $artifact->text_content;
            
            static::log('ContentSearchService: Checking artifact with regex', [
                'artifact_id' => $artifact->id,
                'text_length' => strlen($textContent),
                'text_preview' => $this->repository->getTextSample($textContent, 100),
            ]);

            $matches = [];
            if (preg_match_all($pattern, $textContent, $matches, PREG_SET_ORDER)) {
                $firstMatch = $matches[0][1] ?? $matches[0][0]; // Use capture group if available
                $allMatches = array_map(fn($match) => $match[1] ?? $match[0], $matches);

                static::log('ContentSearchService: Regex matches found', [
                    'artifact_id' => $artifact->id,
                    'match_count' => count($matches),
                    'first_match' => $firstMatch,
                ]);

                return ContentSearchResult::regexFound($firstMatch, $artifact, $pattern, $allMatches);
            }
        }

        static::log('ContentSearchService: No regex matches found');
        return ContentSearchResult::notFound("Pattern '{$pattern}' not found");
    }

    /**
     * Search artifacts using LLM-based text extraction
     */
    private function searchArtifactsWithLlm(ContentSearchRequest $request): ContentSearchResult
    {
        $artifacts = $request->getArtifacts()->filter(function ($artifact) {
            return !empty($artifact->text_content);
        })->sortBy(function ($artifact) {
            return strlen($artifact->text_content);
        });

        static::log('ContentSearchService: Starting LLM search on artifacts', [
            'query' => $request->getNaturalLanguageQuery(),
            'artifacts_with_text' => $artifacts->count(),
            'model' => $request->getLlmModel() ?: 'default',
        ]);

        foreach ($artifacts as $artifact) {
            static::log('ContentSearchService: Processing artifact with LLM', [
                'artifact_id' => $artifact->id,
                'text_length' => strlen($artifact->text_content),
                'text_preview' => $this->repository->getTextSample($artifact->text_content, 200),
            ]);

            // Check if text has potential matches before using LLM
            if (!$this->hasLlmPotentialMatches($artifact->text_content, $request)) {
                static::log('ContentSearchService: No potential matches in artifact text', [
                    'artifact_id' => $artifact->id,
                ]);
                continue;
            }

            try {
                $result = $this->extractWithLlm($artifact->text_content, $request, $artifact);
                if ($result->isFound()) {
                    static::log('ContentSearchService: LLM extraction successful', [
                        'artifact_id' => $artifact->id,
                        'extracted_value' => $result->getValue(),
                        'confidence' => $result->getConfidenceScore(),
                    ]);
                    return $result;
                }
            } catch (Exception $e) {
                static::log('ContentSearchService: LLM extraction failed for artifact', [
                    'artifact_id' => $artifact->id,
                    'error' => $e->getMessage(),
                ]);
                // Continue to next artifact
                continue;
            }
        }

        static::log('ContentSearchService: LLM search found no matches in artifacts');
        return ContentSearchResult::notFound('LLM extraction found no matches');
    }

    /**
     * Search directives using LLM (searches all directive text at once)
     */
    private function searchDirectivesWithLlm(ContentSearchRequest $request): ContentSearchResult
    {
        $directives = $request->getDirectives()->filter(function ($directive) {
            return !empty($directive->directive_text);
        });

        if ($directives->isEmpty()) {
            return ContentSearchResult::notFound('No directives with text content');
        }

        static::log('ContentSearchService: Starting LLM search on directives', [
            'query' => $request->getNaturalLanguageQuery(),
            'directive_count' => $directives->count(),
        ]);

        // Combine all directive texts for bulk processing
        $combinedText = $directives->map(function ($directive) {
            return "DIRECTIVE_{$directive->id}: " . $directive->directive_text;
        })->join("\n\n");

        static::log('ContentSearchService: Processing combined directive text with LLM', [
            'combined_text_length' => strlen($combinedText),
            'text_preview' => $this->repository->getTextSample($combinedText, 300),
        ]);

        try {
            $result = $this->extractWithLlm($combinedText, $request);
            if ($result->isFound()) {
                // Try to identify which directive contained the result
                $sourceDirective = $this->identifySourceDirective($result->getValue(), $directives);
                if ($sourceDirective) {
                    $result->setSourceDirective($sourceDirective, 'directive_text');
                }

                static::log('ContentSearchService: LLM extraction successful from directives', [
                    'extracted_value' => $result->getValue(),
                    'source_directive_id' => $sourceDirective?->id,
                ]);
                return $result;
            }
        } catch (Exception $e) {
            static::log('ContentSearchService: LLM extraction failed for directives', [
                'error' => $e->getMessage(),
            ]);
            throw new ContentExtractionException('LLM directive search', $e->getMessage(), $e);
        }

        return ContentSearchResult::notFound('LLM extraction found no matches in directives');
    }

    /**
     * Search single directive by regex pattern
     */
    private function searchDirectiveByRegex($directive, ContentSearchRequest $request): ContentSearchResult
    {
        $pattern = $request->getRegexPattern();
        $directiveText = $directive->directive_text;

        static::log('ContentSearchService: Searching directive with regex', [
            'directive_id' => $directive->id,
            'pattern' => $pattern,
            'text_length' => strlen($directiveText),
        ]);

        $matches = [];
        if (preg_match_all($pattern, $directiveText, $matches, PREG_SET_ORDER)) {
            $firstMatch = $matches[0][1] ?? $matches[0][0];
            $allMatches = array_map(fn($match) => $match[1] ?? $match[0], $matches);

            static::log('ContentSearchService: Regex matches found in directive', [
                'directive_id' => $directive->id,
                'match_count' => count($matches),
                'first_match' => $firstMatch,
            ]);

            return ContentSearchResult::regexFound($firstMatch, $directive, $pattern, $allMatches);
        }

        return ContentSearchResult::notFound();
    }

    /**
     * Extract content using LLM agent
     */
    private function extractWithLlm(string $textContent, ContentSearchRequest $request, ?Artifact $sourceArtifact = null): ContentSearchResult
    {
        static::log('ContentSearchService: Starting LLM extraction', [
            'text_length' => strlen($textContent),
            'query' => $request->getNaturalLanguageQuery(),
            'model' => $request->getLlmModel(),
        ]);

        $model = $request->getLlmModel() ?: config('google-docs.file_id_detection_model', 'gpt-4o-mini');
        $taskDefinition = $request->getTaskDefinition();

        // Create detection agent
        $agent = Agent::factory()->create([
            'team_id' => $taskDefinition->team_id,
            'name' => 'Content Search Agent',
            'model' => $model,
        ]);

        $agentThread = AgentThread::factory()->create([
            'agent_id' => $agent->id,
            'name' => 'Content Search',
        ]);

        static::log('ContentSearchService: LLM agent created', [
            'agent_id' => $agent->id,
            'thread_id' => $agentThread->id,
            'model' => $model,
        ]);

        $instructions = $this->buildLlmInstructions($request->getNaturalLanguageQuery(), $textContent);

        app(ThreadRepository::class)->addMessageToThread($agentThread, $instructions);

        static::log('ContentSearchService: Running LLM agent');

        // Run the agent thread
        $artifact = $this->runAgentThread($agentThread);

        if (!$artifact || !$artifact->text_content) {
            static::log('ContentSearchService: LLM agent failed to provide response');
            return ContentSearchResult::notFound('LLM agent failed to respond');
        }

        $response = trim($artifact->text_content);
        static::log('ContentSearchService: LLM agent response received', [
            'response' => $response,
            'response_length' => strlen($response),
        ]);

        // Handle "NONE" response
        if (strtoupper($response) === 'NONE' || empty($response)) {
            static::log('ContentSearchService: LLM found no content');
            return ContentSearchResult::notFound('LLM found no matching content');
        }

        $result = ContentSearchResult::llmFound($response, $sourceArtifact, $model);
        $result->addMetadata('llm_instructions', $instructions);
        
        static::log('ContentSearchService: LLM extraction completed', [
            'extracted_value' => $response,
            'confidence' => $result->getConfidenceScore(),
        ]);

        return $result;
    }

    /**
     * Run agent thread and return the artifact
     */
    private function runAgentThread(AgentThread $agentThread): ?Artifact
    {
        $threadRun = app(AgentThreadService::class)->run($agentThread);

        if ($threadRun->lastMessage) {
            return $threadRun->lastMessage->artifact;
        }

        return null;
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
INSTRUCTIONS;
    }

    /**
     * Check if text content has potential matches for LLM processing
     */
    private function hasLlmPotentialMatches(string $textContent, ContentSearchRequest $request): bool
    {
        // Get potential patterns from request options or use defaults
        $patterns = $request->getOption('llm_filter_patterns', []);
        
        if (empty($patterns)) {
            // Default: process all non-empty text
            return !empty(trim($textContent));
        }

        foreach ($patterns as $pattern) {
            if (stripos($textContent, $pattern) !== false) {
                return true;
            }
        }

        return false;
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
                static::log('ContentSearchService: Validation failed for extracted value', [
                    'value' => $result->getValue(),
                    'source' => $result->getSourceIdentifier(),
                ]);
                return false;
            }
            
            return true;
        } catch (Exception $e) {
            $result->setValidated(false, $e->getMessage());
            
            if ($request->isValidationRequired()) {
                static::log('ContentSearchService: Validation error', [
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
    private function identifySourceDirective(string $extractedValue, SupportCollection $directives): ?TaskDefinitionDirective
    {
        foreach ($directives as $directive) {
            if (stripos($directive->directive_text, $extractedValue) !== false) {
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
        $result = null;
        $searchTarget = $request->getSearchTarget();

        static::log('ContentSearchService: Performing search', [
            'search_target' => $searchTarget,
        ]);

        if ($searchTarget === 'artifacts') {
            $result = $this->searchArtifacts($request);
        } elseif ($searchTarget === 'directives') {
            $result = $this->searchDirectives($request);
        } else {
            throw new InvalidSearchParametersException('searchTarget', "Unknown search target: {$searchTarget}");
        }

        static::log('ContentSearchService: Search completed', [
            'found' => $result->isFound(),
            'value' => $result->getValue(),
            'source' => $result->getSourceIdentifier(),
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

        return $this->repository->validateTeamAccess(
            $teamId,
            $request->getArtifacts(),
            $request->getTaskDefinition()
        );
    }

    /**
     * Get active search methods for logging
     */
    private function getActiveSearchMethods(ContentSearchRequest $request): array
    {
        $methods = [];
        
        if ($request->usesFieldPath()) {
            $methods[] = 'field_path';
        }
        
        if ($request->usesRegexPattern()) {
            $methods[] = 'regex';
        }
        
        if ($request->usesLlmExtraction()) {
            $methods[] = 'llm';
        }
        
        return $methods;
    }

    /**
     * Search with retry logic and multiple attempts
     */
    public function searchWithRetry(ContentSearchRequest $request): ContentSearchResult
    {
        $maxAttempts = $request->getMaxAttempts();
        $lastResult = null;

        static::log('ContentSearchService: Starting search with retry', [
            'max_attempts' => $maxAttempts,
        ]);

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            static::log('ContentSearchService: Search attempt', [
                'attempt' => $attempt,
                'max_attempts' => $maxAttempts,
            ]);

            try {
                $result = $this->search($request);
                $result->incrementAttempts();

                if ($result->isSuccessful()) {
                    static::log('ContentSearchService: Search successful on attempt', [
                        'attempt' => $attempt,
                        'value' => $result->getValue(),
                    ]);
                    return $result;
                }

                $lastResult = $result;
            } catch (Exception $e) {
                static::log('ContentSearchService: Search attempt failed', [
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);

                if ($attempt === $maxAttempts) {
                    throw $e;
                }
            }
        }

        static::log('ContentSearchService: All search attempts failed');
        return $lastResult ?: ContentSearchResult::notFound('All search attempts failed');
    }
}