<?php

namespace App\Services\Task\Runners;

use App\Models\Task\Artifact;
use App\Services\FilterService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log as LaravelLog;
use Newms87\Danx\Exceptions\ValidationError;

/**
 * Task runner that filters artifacts based on their content
 */
class FilterArtifactsTaskRunner extends BaseTaskRunner
{
    /**
     * Whether to keep artifacts that match the filter conditions (true) or discard them (false)
     */
    protected bool $keep = true;

    /**
     * The filter service used to evaluate conditions
     */
    protected FilterService $filterService;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->filterService = new FilterService();
    }

    /**
     * Validate the task configuration
     *
     * @param array $config Task configuration
     * @throws ValidationError if the configuration is invalid
     */
    public function validateConfig(array $config): void
    {
        static::log("Validating filter config");

        if (!isset($config['conditions'])) {
            throw new ValidationError("Filter config must have a 'conditions' property");
        }

        if (!is_array($config['conditions'])) {
            throw new ValidationError("Filter config 'conditions' must be an array");
        }

        if (empty($config['conditions'])) {
            throw new ValidationError("Filter config 'conditions' cannot be empty");
        }

        // Validate each condition or condition group
        foreach($config['conditions'] as $condition) {
            if (!isset($condition['type'])) {
                throw new ValidationError("Each condition must have a 'type' property");
            }

            if ($condition['type'] === 'condition') {
                $this->filterService->validateCondition($condition);
            } elseif ($condition['type'] === 'condition_group') {
                $this->validateConditionGroup($condition);
            } else {
                throw new ValidationError("Condition type must be one of: condition, condition_group");
            }
        }

        // Validate the operator
        if (isset($config['operator']) && !in_array($config['operator'], ['AND', 'OR'])) {
            throw new ValidationError("Filter 'operator' must be one of: AND, OR");
        }

        // Validate the action
        if (isset($config['action']) && !in_array($config['action'], ['keep', 'discard'])) {
            throw new ValidationError("Filter 'action' must be one of: keep, discard");
        }

        static::log("Filter config validated successfully");
    }

    /**
     * Validate a condition group
     *
     * @param array $group The condition group to validate
     * @throws ValidationError if the group is invalid
     */
    protected function validateConditionGroup(array $group): void
    {
        if (!isset($group['conditions'])) {
            throw new ValidationError("Condition group must have a 'conditions' property");
        }

        if (!is_array($group['conditions'])) {
            throw new ValidationError("Condition group 'conditions' must be an array");
        }

        if (empty($group['conditions'])) {
            throw new ValidationError("Condition group 'conditions' cannot be empty");
        }

        // Validate each condition in the group
        foreach($group['conditions'] as $condition) {
            if (!isset($condition['type'])) {
                throw new ValidationError("Each condition in group must have a 'type' property");
            }

            if ($condition['type'] === 'condition') {
                $this->filterService->validateCondition($condition);
            } elseif ($condition['type'] === 'condition_group') {
                $this->validateConditionGroup($condition);
            } else {
                throw new ValidationError("Condition type in group must be one of: condition, condition_group");
            }
        }

        // Validate the operator
        if (isset($group['operator']) && !in_array($group['operator'], ['AND', 'OR'])) {
            throw new ValidationError("Condition group 'operator' must be one of: AND, OR");
        }
    }

    /**
     * Configure the task runner
     *
     * @param array $config Task configuration
     */
    public function configure(array $config): void
    {
        static::log("Configuring filter task runner");

        $this->keep = $config['action'] ?? 'keep' === 'keep';

        static::log("Filter task runner configured to " . ($this->keep ? "keep" : "discard") . " matching artifacts");
    }

    /**
     * Process a single artifact
     *
     * @param Artifact $artifact The artifact to process
     * @return bool Whether the artifact should be kept (true) or discarded (false)
     */
    public function processArtifact(Artifact $artifact): bool
    {
        $config     = $this->taskDefinition->config;
        $operator   = $config['operator'] ?? 'AND';
        $conditions = $config['conditions'] ?? [];

        $matches = $this->evaluateConditions($artifact, $conditions, $operator);

        // If we're keeping matches, return matches; if we're discarding matches, return !matches
        $keep = $this->keep ? $matches : !$matches;

        static::log("Artifact {$artifact->id} " . ($keep ? "kept" : "discarded"));

        return $keep;
    }

    /**
     * Get the field value from the artifact
     *
     * @param Artifact   $artifact         The artifact to get the field value from
     * @param string     $field            The field to get the value from
     * @param array|null $fragmentSelector The fragment selector to apply
     * @return mixed The field value
     */
    protected function getFieldValue(Artifact $artifact, string $field, ?array $fragmentSelector = null): mixed
    {
        switch($field) {
            case 'text_content':
                return $artifact->text_content;

            case 'json_content':
                if (empty($artifact->json_content)) {
                    return null;
                }

                $data = $artifact->json_content;

                if ($fragmentSelector) {
                    $jsonSchemaService = app(\App\Services\JsonSchema\JsonSchemaService::class);

                    return $jsonSchemaService->filterDataByFragmentSelector($data, $fragmentSelector);
                }

                return $data;

            case 'meta':
                if (empty($artifact->meta)) {
                    return null;
                }

                $data = $artifact->meta;

                if ($fragmentSelector) {
                    $jsonSchemaService = app(\App\Services\JsonSchema\JsonSchemaService::class);

                    return $jsonSchemaService->filterDataByFragmentSelector($data, $fragmentSelector);
                }

                return $data;

            case 'storedFiles':
                return $artifact->storedFiles;

            default:
                return null;
        }
    }

    /**
     * Evaluate conditions against an artifact
     *
     * @param Artifact $artifact   The artifact to evaluate the conditions against
     * @param array    $conditions The conditions to evaluate
     * @param string   $operator   The operator to use (AND or OR)
     * @return bool Whether the artifact matches the conditions
     */
    protected function evaluateConditions(Artifact $artifact, array $conditions, string $operator = 'AND'): bool
    {
        if (empty($conditions)) {
            return false;
        }

        $results = [];

        foreach($conditions as $condition) {
            if ($condition['type'] === 'condition') {
                // Adapt the condition for FilterService by adding field values
                $adaptedCondition = $this->adaptConditionForFilterService($artifact, $condition);
                $result           = $this->filterService->evaluateCondition($artifact, $adaptedCondition);
                $results[]        = $result;
                static::log("Condition evaluated as: " . ($result ? "true" : "false") .
                    " with field={$condition['field']} operator={$condition['operator']}");
            } elseif ($condition['type'] === 'condition_group') {
                $groupOperator = $condition['operator'] ?? 'AND';
                $result        = $this->evaluateConditions($artifact, $condition['conditions'], $groupOperator);
                $results[]     = $result;
                static::log("Condition group evaluated as: " . ($result ? "true" : "false"));
            }
        }

        if (empty($results)) {
            return false;
        }

        if ($operator === 'AND') {
            return !in_array(false, $results, true);
        } else { // OR
            return in_array(true, $results, true);
        }
    }

    /**
     * Adapt a condition for the FilterService by prepopulating field values
     *
     * @param Artifact $artifact  The artifact to get field values from
     * @param array    $condition The condition to adapt
     * @return array The adapted condition
     */
    protected function adaptConditionForFilterService(Artifact $artifact, array $condition): array
    {
        // Clone the condition to avoid modifying the original
        $adaptedCondition = $condition;

        // Add the field value to the condition
        $fieldValue = $this->getFieldValue(
            $artifact,
            $condition['field'],
            $condition['fragment_selector'] ?? null
        );

        $adaptedCondition['field_value'] = $fieldValue;

        return $adaptedCondition;
    }

    /**
     * Log a message
     *
     * @param string $message The message to log
     */
    public static function log(string $message): void
    {
        $timestamp = Carbon::now()->format('Y-m-d H:i:s');
        LaravelLog::info("[FilterArtifactsTaskRunner] [$timestamp] $message");
    }
}
