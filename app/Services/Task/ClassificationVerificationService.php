<?php

namespace App\Services\Task;

use App\Models\Agent\Agent;
use App\Models\Schema\SchemaDefinition;
use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
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

        return Agent::updateOrCreate(
            [
                'team_id' => null, // System-level agent, no team context
                'name'    => $agentName,
            ],
            [
                'model'       => $model,
                'description' => 'Automated agent for classification verification and correction',
                'retry_count' => 2,
            ]
        );
    }

    /**
     * Verify a specific classification property across artifacts
     */
    public function verifyClassificationProperty(Collection $artifacts, string $property): void
    {
        static::logDebug("Starting classification verification for property '$property' across " . $artifacts->count() . ' artifacts');

        $verificationGroups = $this->buildVerificationGroups($artifacts, $property);

        if (empty($verificationGroups)) {
            static::logDebug("No verification groups found for property '$property'");

            return;
        }

        static::logDebug('Found ' . count($verificationGroups) . " verification groups for property '$property'");

        // Ensure agent is created by calling getOrCreateClassificationVerificationAgent
        $this->getOrCreateClassificationVerificationAgent();

        foreach ($verificationGroups as $groupIndex => $group) {
            static::logDebug('Processing verification group ' . ($groupIndex + 1) . ' of ' . count($verificationGroups));
            $this->verifyClassificationGroup($group, $property);
        }

        static::logDebug("Classification verification completed for property '$property'");
    }

    /**
     * Create separate TaskProcesses for each property to verify based on task_runner_config
     */
    public function createVerificationProcessesForTaskRun(TaskRun $taskRun): void
    {
        static::logDebug("Creating verification processes for TaskRun {$taskRun->id}");

        $taskDefinition = $taskRun->taskDefinition;
        $verifyConfig   = $taskDefinition->task_runner_config['verify'] ?? [];

        if (empty($verifyConfig)) {
            static::logDebug('No verify config found in task_runner_config');

            return;
        }

        if (!is_array($verifyConfig)) {
            static::logDebug('Invalid verify config - must be an array of property names');

            return;
        }

        static::logDebug('Found verify config for properties: ' . implode(', ', $verifyConfig));

        // Check if artifacts with classification metadata exist
        $artifacts = $taskRun->outputArtifacts()
            ->whereNotNull('meta->classification')
            ->get();

        if ($artifacts->isEmpty()) {
            static::logDebug('No artifacts with classification metadata found');

            return;
        }

        $availableProperties = array_keys($artifacts->first()->meta['classification'] ?? []);
        $propertiesToVerify  = array_intersect($verifyConfig, $availableProperties);

        if (empty($propertiesToVerify)) {
            static::logDebug('No matching properties found between verify config and classification metadata');

            return;
        }

        $processesCreated = 0;
        foreach ($propertiesToVerify as $property) {
            // Check if this property actually has outliers that need verification
            $verificationGroups = $this->buildVerificationGroups($artifacts, $property);

            if (empty($verificationGroups)) {
                static::logDebug("Skipping property '$property' - no outliers detected that need verification");

                continue;
            }

            static::logDebug("Property '$property' has " . count($verificationGroups) . ' outlier groups - creating verification process');

            $taskRun->taskProcesses()->create([
                'name'     => "Classification Verification: $property",
                'meta'     => ['classification_verification_property' => $property],
                'is_ready' => true,
            ]);

            $processesCreated++;
        }

        if ($processesCreated > 0) {
            $taskRun->updateRelationCounter('taskProcesses');
        }

        static::logDebug("Created $processesCreated verification processes");
    }

    /**
     * Build verification groups with context window (previous 2, current, next 1 artifacts)
     * Only include groups where values differ from adjacent artifacts
     */
    protected function buildVerificationGroups(Collection $artifacts, string $property): array
    {
        $groups         = [];
        $artifactsArray = $artifacts->values()->all();
        $count          = count($artifactsArray);

        for ($i = 0; $i < $count; $i++) {
            $currentArtifact = $artifactsArray[$i];
            $currentValue    = $this->getPropertyValue($currentArtifact, $property);

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
                    'value'    => $this->getPropertyValue($artifactsArray[$j], $property),
                ];
            }

            // Add current artifact
            $contextArtifacts[] = [
                'artifact' => $currentArtifact,
                'position' => 'current',
                'value'    => $currentValue,
            ];

            // Add next 1 artifact
            if ($i + 1 < $count) {
                $contextArtifacts[] = [
                    'artifact' => $artifactsArray[$i + 1],
                    'position' => 'next',
                    'value'    => $this->getPropertyValue($artifactsArray[$i + 1], $property),
                ];
            }

            $groups[] = [
                'focus_artifact_id' => $currentArtifact->id,
                'focus_position'    => $i,
                'context'           => $contextArtifacts,
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
        $value          = $classification[$property]        ?? null;

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
        static::logDebug("Verifying classification group for artifact $focusArtifactId, property '$property'");

        $prompt = $this->buildVerificationPrompt($group, $property);

        $threadRepository = app(ThreadRepository::class);
        $agent            = $this->getOrCreateClassificationVerificationAgent();
        $agentThread      = $threadRepository->create($agent, 'Classification Verification');

        $systemMessage = 'You are a classification verification assistant. Your job is to review classification values in context and determine if any need correction for consistency and accuracy.';
        $threadRepository->addMessageToThread($agentThread, $systemMessage);
        $threadRepository->addMessageToThread($agentThread, $prompt);

        // Get the response schema for verification
        $responseSchema = $this->getVerificationResponseSchema();

        // Run the thread with JSON schema response format
        $threadRun = (new AgentThreadService())
            ->withResponseFormat($responseSchema)
            ->withTimeout(config('ai.classification_verification.timeout'))
            ->run($agentThread);

        if (!$threadRun->lastMessage || !$threadRun->lastMessage->content) {
            static::logDebug('Failed to get response from AI agent for classification verification');

            return;
        }

        try {
            $jsonContent = $threadRun->lastMessage->getJsonContent();

            if (!isset($jsonContent['corrections']) || !is_array($jsonContent['corrections'])) {
                static::logDebug('No corrections provided in verification response');

                return;
            }

            $this->applyVerificationCorrections($group, $jsonContent['corrections'], $property);

        } catch (\Exception $e) {
            static::logDebug('Error parsing verification response: ' . $e->getMessage());
        }
    }

    /**
     * Build the verification prompt for a group of artifacts
     */
    protected function buildVerificationPrompt(array $group, string $property): string
    {
        $contextData = [];

        /** @var TaskDefinition $taskDefinition */
        $taskDefinition = $group['context'][0]['artifact']?->taskDefinition;

        foreach ($group['context'] as $contextItem) {
            /** @var Artifact $artifact */
            $artifact = $contextItem['artifact'];
            $position = $contextItem['position'];
            $value    = $contextItem['value'];

            $contextData[] = [
                'position'    => $position,
                'artifact_id' => $artifact->id,
                'value'       => $value,
                'content'     => $artifact->text_content ?: $artifact->json_content,
            ];
        }

        $contextJson = json_encode($contextData, JSON_PRETTY_PRINT);

        $propertyRule = $taskDefinition->schemaDefinition->schema['properties'][$property]['description'] ?? '(Property rule not found)';

        return <<<PROMPT
I need you to verify if the CORRECT classification values were given for the property "$property" based on the full context of adjacent artifacts.

**CRITICAL**: This is NOT about formatting or consistency - it's about correctness. Classifications may have been ambiguous when processed in isolation, but with the full context, you can now determine if BETTER or MORE ACCURATE classifications should have been given.

**Context Window:**

$contextJson

**Classification Rule for "$property":**

$propertyRule

**Your Task:**
1. Review ALL artifacts in the context window (previous, current, and next)
2. Examine each artifact's content and current classification value
3. Determine if ANY of the artifacts have incorrect classifications based on the full context
4. The context may reveal information that makes ANY artifact's classification clearly incorrect

**Key Scenarios to Look For:**
- Any artifact's classification was made without knowing the broader context
- The full context reveals information that changes how any artifact should be classified
- Adjacent artifacts provide clarifying information that affects interpretation
- The classification rule, when applied with full context, points to different values

**Important Guidelines:**
- You can correct ANY artifact in the context window (previous, current, or next)
- ONLY suggest corrections if the context reveals the classification is actually WRONG according to the rule
- Ignore minor formatting differences - focus on semantic correctness
- Context should provide evidence that a different classification value is more accurate
- If all classifications are correct given the context, return empty corrections array

The goal is accuracy based on context. You may correct multiple artifacts if needed.
PROMPT;
    }

    /**
     * Apply verification corrections to artifacts
     */
    protected function applyVerificationCorrections(array $group, array $corrections, string $property): void
    {
        if (empty($corrections)) {
            static::logDebug("No corrections to apply for property '$property'");

            return;
        }

        static::logDebug('Applying ' . count($corrections) . " corrections for property '$property'");

        $previousArtifactsCorrected = [];

        foreach ($corrections as $correction) {
            if (!isset($correction['artifact_id']) || !isset($correction['corrected_value'])) {
                static::logDebug('Invalid correction format - missing artifact_id or corrected_value');

                continue;
            }

            $artifactId     = $correction['artifact_id'];
            $correctedValue = $correction['corrected_value'];
            $reason         = $correction['reason'] ?? 'No reason provided';

            // Find the artifact and its position in the context
            $artifact         = null;
            $artifactPosition = null;
            foreach ($group['context'] as $contextItem) {
                if ($contextItem['artifact']->id == $artifactId) {
                    $artifact         = $contextItem['artifact'];
                    $artifactPosition = $contextItem['position'];
                    break;
                }
            }

            if (!$artifact) {
                static::logDebug("Artifact $artifactId not found in verification group");

                continue;
            }

            $originalValue = $this->getPropertyValue($artifact, $property);

            if ($originalValue === $correctedValue) {
                static::logDebug("Artifact $artifactId already has correct value '$correctedValue'");

                continue;
            }

            // Update the artifact's classification, preserving structure
            $meta         = $artifact->meta;
            $currentValue = $meta['classification'][$property] ?? null;

            // Preserve complex structure if present (e.g., objects with name, reasoning, confidence)
            if (is_array($currentValue)) {
                if (isset($currentValue['name'])) {
                    // Update only the name field, preserve reasoning and confidence
                    $meta['classification'][$property]['name'] = $correctedValue;
                } elseif (isset($currentValue['id'])) {
                    // Update only the id field, preserve other fields
                    $meta['classification'][$property]['id'] = $correctedValue;
                } else {
                    // Array without name/id structure, replace entirely
                    $meta['classification'][$property] = $correctedValue;
                }
            } else {
                // Simple string value, replace entirely
                $meta['classification'][$property] = $correctedValue;
            }

            $artifact->meta = $meta;
            $artifact->save();

            static::logDebug("Updated artifact $artifactId property '$property': '$originalValue' => '$correctedValue' (Reason: $reason)");

            // Track if we corrected a previous artifact
            if ($artifactPosition === 'previous') {
                $previousArtifactsCorrected[] = $artifact;
            }
        }

        // If we corrected any previous artifacts, create new verification processes for them
        if (!empty($previousArtifactsCorrected)) {
            $this->createRecursiveVerificationProcesses($previousArtifactsCorrected, $property);
        }
    }

    /**
     * Create recursive verification processes for corrected previous artifacts
     */
    protected function createRecursiveVerificationProcesses(array $correctedArtifacts, string $property): void
    {
        static::logDebug('Creating recursive verification processes for ' . count($correctedArtifacts) . ' corrected previous artifacts');

        foreach ($correctedArtifacts as $artifact) {
            // Get the TaskRun this artifact belongs to
            $taskRun = $artifact->taskRun;
            if (!$taskRun) {
                static::logDebug("No TaskRun found for artifact {$artifact->id}");

                continue;
            }

            // Create a new task process for recursive verification
            $taskProcess = $taskRun->taskProcesses()->create([
                'name'     => "Recursive Classification Verification: $property (Artifact {$artifact->id})",
                'meta'     => [
                    'classification_verification_property' => $property,
                    'recursive_verification_artifact_id'   => $artifact->id,
                    'is_recursive'                         => true,
                ],
                'is_ready' => true,
            ]);

            static::logDebug("Created recursive verification process {$taskProcess->id} for artifact {$artifact->id}");

            // Update the TaskRun's process counter
            $taskRun->updateRelationCounter('taskProcesses');
        }
    }

    /**
     * Get the JSON schema for verification responses
     */
    protected function getVerificationResponseSchema(): SchemaDefinition
    {
        $schema = [
            'type'                 => 'object',
            'properties'           => [
                'corrections' => [
                    'type'        => 'array',
                    'items'       => [
                        'type'                 => 'object',
                        'properties'           => [
                            'artifact_id'     => [
                                'type'        => 'integer',
                                'description' => 'The ID of the artifact to correct',
                            ],
                            'corrected_value' => [
                                'type'        => 'string',
                                'description' => 'The corrected classification value',
                            ],
                            'reason'          => [
                                'type'        => 'string',
                                'description' => 'Brief explanation of why the correction was made',
                            ],
                        ],
                        'required'             => ['artifact_id', 'corrected_value', 'reason'],
                        'additionalProperties' => false,
                    ],
                    'description' => 'Array of classification corrections to apply',
                ],
            ],
            'required'             => ['corrections'],
            'additionalProperties' => false,
        ];

        return SchemaDefinition::firstOrCreate([
            'team_id' => null, // System-level schema, not team-specific
            'name'    => 'Classification Verification Response',
        ], [
            'description' => 'JSON schema for classification verification responses',
            'schema'      => $schema,
        ]);
    }
}
