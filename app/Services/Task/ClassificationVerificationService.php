<?php

namespace App\Services\Task;

use App\Models\Agent\Agent;
use App\Models\Schema\SchemaDefinition;
use App\Models\Task\Artifact;
use App\Models\Task\TaskRun;
use App\Repositories\ThreadRepository;
use App\Services\AgentThread\AgentThreadService;
use App\Traits\HasDebugLogging;
use Illuminate\Support\Collection;

class ClassificationVerificationService
{
    use HasDebugLogging;

    /**
     * Get or create the classification verification agent based on config
     */
    public function getOrCreateClassificationVerificationAgent(): Agent
    {
        $config    = config('ai.classification_verification');
        $agentName = $config['agent_name'];
        $model     = $config['model'];

        // Find existing agent by name and model (no team context needed)
        $agent = Agent::whereNull('team_id')
            ->where('name', $agentName)
            ->first();

        if (!$agent) {
            // Create agent directly with explicit null team_id
            $agent = Agent::create([
                'name'        => $agentName,
                'model'       => $model,
                'description' => 'Automated agent for classification verification and correction',
                'api_options' => ['temperature' => 0],
                'team_id'     => null,
                'retry_count' => 2,
            ]);
            static::log("Created new classification verification agent: $agent->name (ID: $agent->id)");
        }

        return $agent;
    }

    /**
     * Verify a specific classification property across artifacts
     */
    public function verifyClassificationProperty(Collection $artifacts, string $property): void
    {
        static::log("Starting classification verification for property '$property' across " . $artifacts->count() . ' artifacts');

        $verificationGroups = $this->buildVerificationGroups($artifacts, $property);

        if (empty($verificationGroups)) {
            static::log("No verification groups found for property '$property'");
            return;
        }

        static::log('Found ' . count($verificationGroups) . " verification groups for property '$property'");

        // Ensure agent is created by calling getOrCreateClassificationVerificationAgent
        $this->getOrCreateClassificationVerificationAgent();

        foreach ($verificationGroups as $groupIndex => $group) {
            static::log("Processing verification group " . ($groupIndex + 1) . " of " . count($verificationGroups));
            $this->verifyClassificationGroup($group, $property);
        }

        static::log("Classification verification completed for property '$property'");
    }

    /**
     * Create separate TaskProcesses for each property to verify based on task_runner_config
     */
    public function createVerificationProcessesForTaskRun(TaskRun $taskRun): void
    {
        static::log("Creating verification processes for TaskRun {$taskRun->id}");

        $taskDefinition = $taskRun->taskDefinition;
        $verifyConfig = $taskDefinition->task_runner_config['verify'] ?? [];

        if (empty($verifyConfig)) {
            static::log('No verify config found in task_runner_config');
            return;
        }

        if (!is_array($verifyConfig)) {
            static::log('Invalid verify config - must be an array of property names');
            return;
        }

        static::log('Found verify config for properties: ' . implode(', ', $verifyConfig));

        // Check if artifacts with classification metadata exist
        $artifacts = $taskRun->outputArtifacts()
            ->whereNotNull('meta->classification')
            ->get();

        if ($artifacts->isEmpty()) {
            static::log('No artifacts with classification metadata found');
            return;
        }

        $availableProperties = array_keys($artifacts->first()->meta['classification'] ?? []);
        $propertiesToVerify = array_intersect($verifyConfig, $availableProperties);

        if (empty($propertiesToVerify)) {
            static::log('No matching properties found between verify config and classification metadata');
            return;
        }

        $processesCreated = 0;
        foreach ($propertiesToVerify as $property) {
            // Check if this property actually has outliers that need verification
            $verificationGroups = $this->buildVerificationGroups($artifacts, $property);
            
            if (empty($verificationGroups)) {
                static::log("Skipping property '$property' - no outliers detected that need verification");
                continue;
            }

            static::log("Property '$property' has " . count($verificationGroups) . " outlier groups - creating verification process");
            
            $taskRun->taskProcesses()->create([
                'name' => "Classification Verification: $property",
                'meta' => ['classification_verification_property' => $property],
            ]);
            
            $processesCreated++;
        }

        if ($processesCreated > 0) {
            $taskRun->updateRelationCounter('taskProcesses');
        }

        static::log("Created $processesCreated verification processes");
    }

    /**
     * Build verification groups with context window (previous 2, current, next 1 artifacts)
     * Only include groups where values differ from adjacent artifacts
     */
    protected function buildVerificationGroups(Collection $artifacts, string $property): array
    {
        $groups = [];
        $artifactsArray = $artifacts->values()->all();
        $count = count($artifactsArray);

        for ($i = 0; $i < $count; $i++) {
            $currentArtifact = $artifactsArray[$i];
            $currentValue = $this->getPropertyValue($currentArtifact, $property);

            if ($currentValue === null) {
                continue;
            }

            // Check if current value is an outlier that differs from adjacent values
            // Only create groups for artifacts that are likely incorrect outliers
            $isOutlier = false;
            $prevValue = null;
            $nextValue = null;

            // Get previous value if available
            if ($i > 0) {
                $prevValue = $this->getPropertyValue($artifactsArray[$i - 1], $property);
            }

            // Get next value if available
            if ($i + 1 < $count) {
                $nextValue = $this->getPropertyValue($artifactsArray[$i + 1], $property);
            }

            // Determine if this artifact is an outlier that needs verification
            if ($prevValue !== null && $nextValue !== null) {
                // Middle artifact: outlier if it differs from both neighbors AND neighbors agree with each other
                $isOutlier = ($currentValue !== $prevValue && $currentValue !== $nextValue && $prevValue === $nextValue);
            } elseif ($prevValue !== null) {
                // Last artifact: only consider outlier if we have enough context to be confident
                // Look at the pattern - if previous artifact also differs from its previous, less likely to be outlier
                $prevPrevValue = null;
                if ($i >= 2) {
                    $prevPrevValue = $this->getPropertyValue($artifactsArray[$i - 2], $property);
                }
                $isOutlier = ($currentValue !== $prevValue) && 
                            ($prevPrevValue === null || $prevValue === $prevPrevValue);
            } elseif ($nextValue !== null) {
                // First artifact: only consider outlier if we have enough context to be confident
                // Look at the pattern - if next artifact also differs from its next, less likely to be outlier
                $nextNextValue = null;
                if ($i + 2 < $count) {
                    $nextNextValue = $this->getPropertyValue($artifactsArray[$i + 2], $property);
                }
                $isOutlier = ($currentValue !== $nextValue) && 
                            ($nextNextValue === null || $nextValue === $nextNextValue);
            }

            if (!$isOutlier) {
                continue;
            }

            // Build context window: previous 2, current, next 1
            $contextArtifacts = [];

            // Add previous 2 artifacts
            for ($j = max(0, $i - 2); $j < $i; $j++) {
                $contextArtifacts[] = [
                    'artifact' => $artifactsArray[$j],
                    'position' => 'previous',
                    'value' => $this->getPropertyValue($artifactsArray[$j], $property),
                ];
            }

            // Add current artifact
            $contextArtifacts[] = [
                'artifact' => $currentArtifact,
                'position' => 'current',
                'value' => $currentValue,
            ];

            // Add next 1 artifact
            if ($i + 1 < $count) {
                $contextArtifacts[] = [
                    'artifact' => $artifactsArray[$i + 1],
                    'position' => 'next',
                    'value' => $this->getPropertyValue($artifactsArray[$i + 1], $property),
                ];
            }

            $groups[] = [
                'focus_artifact_id' => $currentArtifact->id,
                'focus_position' => $i,
                'context' => $contextArtifacts,
            ];
        }

        return $groups;
    }

    /**
     * Get property value from artifact classification metadata
     */
    protected function getPropertyValue(Artifact $artifact, string $property): ?string
    {
        $classification = $artifact->meta['classification'] ?? [];
        $value = $classification[$property] ?? null;

        if (is_string($value)) {
            return trim($value) ?: null;
        }

        if (is_array($value)) {
            if (isset($value['id'])) {
                return trim($value['id']) ?: null;
            }
            if (isset($value['name'])) {
                return trim($value['name']) ?: null;
            }
        }

        return null;
    }

    /**
     * Verify a single group of artifacts with context
     */
    protected function verifyClassificationGroup(array $group, string $property): void
    {
        $focusArtifactId = $group['focus_artifact_id'];
        static::log("Verifying classification group for artifact $focusArtifactId, property '$property'");

        $prompt = $this->buildVerificationPrompt($group, $property);
        
        $threadRepository = app(ThreadRepository::class);
        $agent = $this->getOrCreateClassificationVerificationAgent();
        $agentThread = $threadRepository->create($agent, 'Classification Verification');

        $systemMessage = 'You are a classification verification assistant. Your job is to review classification values in context and determine if any need correction for consistency and accuracy.';
        $threadRepository->addMessageToThread($agentThread, $systemMessage);
        $threadRepository->addMessageToThread($agentThread, $prompt);

        // Get the response schema for verification
        $responseSchema = $this->getVerificationResponseSchema();

        // Run the thread with JSON schema response format
        $threadRun = (new AgentThreadService())
            ->withResponseFormat($responseSchema)
            ->run($agentThread);

        if (!$threadRun->lastMessage || !$threadRun->lastMessage->content) {
            static::log('Failed to get response from AI agent for classification verification');
            return;
        }

        try {
            $jsonContent = $threadRun->lastMessage->getJsonContent();

            if (!isset($jsonContent['corrections']) || !is_array($jsonContent['corrections'])) {
                static::log("No corrections provided in verification response");
                return;
            }

            $this->applyVerificationCorrections($group, $jsonContent['corrections'], $property);

        } catch (\Exception $e) {
            static::log('Error parsing verification response: ' . $e->getMessage());
        }
    }

    /**
     * Build the verification prompt for a group of artifacts
     */
    protected function buildVerificationPrompt(array $group, string $property): string
    {
        $contextData = [];

        foreach ($group['context'] as $contextItem) {
            $artifact = $contextItem['artifact'];
            $position = $contextItem['position'];
            $value = $contextItem['value'];

            // Include some content context for better verification
            $content = $artifact->content;
            if (strlen($content) > 500) {
                $content = substr($content, 0, 500) . '...';
            }

            $contextData[] = [
                'position' => $position,
                'artifact_id' => $artifact->id,
                'value' => $value,
                'content_sample' => $content,
            ];
        }

        $contextJson = json_encode($contextData, JSON_PRETTY_PRINT);

        return <<<PROMPT
I need you to verify the classification value for the property "$property" in the context of adjacent artifacts.

You are reviewing a sequence of artifacts where the CURRENT artifact's classification may need correction based on the context of previous and next artifacts.

The goal is to ensure consistency and accuracy in classification values. Look for:
1. **Inconsistent formatting** - Same entity with different formats
2. **Incorrect classifications** - Wrong value based on content
3. **Missing context** - Value should be more/less specific based on adjacent artifacts
4. **Typos or errors** - Clear mistakes in the classification

**Context Window:**
$contextJson

**Instructions:**
- Focus on the artifact marked as "current"
- Review its classification value in context of the "previous" and "next" artifacts
- Only suggest corrections if there are clear issues
- Maintain consistency with the pattern established by adjacent artifacts
- Consider the content sample to verify the classification accuracy

**Important Rules:**
1. Only correct if there's a clear issue - don't change correct values
2. Prefer consistency with adjacent artifacts when reasonable
3. Base corrections on both content and context
4. If no correction is needed, return an empty corrections array

Provide corrections only for artifacts that clearly need them.
PROMPT;
    }

    /**
     * Apply verification corrections to artifacts
     */
    protected function applyVerificationCorrections(array $group, array $corrections, string $property): void
    {
        if (empty($corrections)) {
            static::log("No corrections to apply for property '$property'");
            return;
        }

        static::log('Applying ' . count($corrections) . " corrections for property '$property'");

        foreach ($corrections as $correction) {
            if (!isset($correction['artifact_id']) || !isset($correction['corrected_value'])) {
                static::log('Invalid correction format - missing artifact_id or corrected_value');
                continue;
            }

            $artifactId = $correction['artifact_id'];
            $correctedValue = $correction['corrected_value'];
            $reason = $correction['reason'] ?? 'No reason provided';

            // Find the artifact in the context
            $artifact = null;
            foreach ($group['context'] as $contextItem) {
                if ($contextItem['artifact']->id == $artifactId) {
                    $artifact = $contextItem['artifact'];
                    break;
                }
            }

            if (!$artifact) {
                static::log("Artifact $artifactId not found in verification group");
                continue;
            }

            $originalValue = $this->getPropertyValue($artifact, $property);
            
            if ($originalValue === $correctedValue) {
                static::log("Artifact $artifactId already has correct value '$correctedValue'");
                continue;
            }

            // Update the artifact's classification
            $meta = $artifact->meta;
            $meta['classification'][$property] = $correctedValue;
            $artifact->meta = $meta;
            $artifact->save();

            static::log("Updated artifact $artifactId property '$property': '$originalValue' => '$correctedValue' (Reason: $reason)");
        }
    }

    /**
     * Get the JSON schema for verification responses
     */
    protected function getVerificationResponseSchema(): SchemaDefinition
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'corrections' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'artifact_id' => [
                                'type' => 'integer',
                                'description' => 'The ID of the artifact to correct',
                            ],
                            'corrected_value' => [
                                'type' => 'string',
                                'description' => 'The corrected classification value',
                            ],
                            'reason' => [
                                'type' => 'string',
                                'description' => 'Brief explanation of why the correction was made',
                            ],
                        ],
                        'required' => ['artifact_id', 'corrected_value', 'reason'],
                        'additionalProperties' => false,
                    ],
                    'description' => 'Array of classification corrections to apply',
                ],
            ],
            'required' => ['corrections'],
            'additionalProperties' => false,
        ];

        return SchemaDefinition::firstOrCreate([
            'team_id' => null, // System-level schema, not team-specific
            'name' => 'Classification Verification Response',
        ], [
            'description' => 'JSON schema for classification verification responses',
            'schema' => $schema,
        ]);
    }
}