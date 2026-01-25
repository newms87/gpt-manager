<?php

namespace App\Services\Task\Runners;

use App\Models\Task\Artifact;
use App\Services\FilterService;
use Newms87\Danx\Traits\HasDebugLogging;
use Illuminate\Support\Collection;
use Newms87\Danx\Exceptions\ValidationError;

/**
 * Task runner that filters artifacts based on their content
 */
class FilterArtifactsTaskRunner extends BaseTaskRunner
{
    use HasDebugLogging;

    const string RUNNER_NAME = 'Filter Artifacts';

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
     * Run the filter task on the input artifacts
     */
    public function run(): void
    {
        $this->activity('Filtering artifacts based on content...', 10);

        $inputArtifacts = $this->taskProcess->inputArtifacts;

        $filterConfig = $this->config('filter_config', []);

        if (empty($filterConfig)) {
            $this->activity('No filter config provided, passing all artifacts through', 100);
            $this->complete($inputArtifacts);

            return;
        }

        // If conditions array is empty, pass all artifacts through
        if (empty($filterConfig['conditions'])) {
            $this->activity('No filter conditions provided, passing all artifacts through', 100);
            $this->complete($inputArtifacts);

            return;
        }

        // Validate the filter config structure
        $this->validateConfig($filterConfig);

        // Apply the configuration to set up filter parameters
        $this->configure($filterConfig);

        // Filter the artifacts
        $filteredArtifacts = $this->filterArtifacts($inputArtifacts, $filterConfig);

        $totalArtifacts = $inputArtifacts->count();
        $filteredCount  = count($filteredArtifacts);

        $this->activity("Filtered $filteredCount out of $totalArtifacts artifacts", 100);

        $this->complete($filteredArtifacts);
    }

    /**
     * Filter artifacts based on filter configuration
     *
     * @param  Collection  $artifacts  Artifacts to filter
     * @param  array  $config  Filter configuration
     * @return array Filtered artifacts
     */
    protected function filterArtifacts(Collection $artifacts, array $config): array
    {
        $filteredArtifacts = [];
        $operator          = strtoupper($config['operator'] ?? 'AND');
        $action            = strtolower($config['action'] ?? 'keep');
        $this->keep        = ($action === 'keep');

        foreach ($artifacts as $artifact) {
            // Evaluate all conditions against the artifact
            $matches = $this->evaluateConditions($artifact, $config['conditions'], $operator);

            // Apply the action (keep or discard)
            if (($this->keep && $matches) || (!$this->keep && !$matches)) {
                $filteredArtifacts[] = $artifact;
            }
        }

        return $filteredArtifacts;
    }

    /**
     * Evaluate conditions against an artifact
     *
     * @param  Artifact  $artifact  The artifact to evaluate the conditions against
     * @param  array  $conditions  The conditions to evaluate
     * @param  string  $operator  The operator to use (AND or OR)
     * @return bool Whether the artifact matches the conditions
     */
    protected function evaluateConditions(Artifact $artifact, array $conditions, string $operator = 'AND'): bool
    {
        static::logDebug("Artifact $artifact->id: Evaluating conditions with operator: $operator");

        if (empty($conditions)) {
            return false;
        }

        $results = [];

        foreach ($conditions as $condition) {
            if ($condition['type'] === 'condition') {
                // Get the field value to evaluate
                $fieldValue = $this->getFieldValue(
                    $artifact,
                    $condition['field'],
                    $condition['fragment_selector'] ?? null
                );

                // Evaluate the condition against the field value
                $result    = $this->filterService->evaluateCondition($fieldValue, $condition);
                $results[] = $result;

                static::logDebug('Condition evaluated as: ' . ($result ? 'true' : 'false'));
            } elseif ($condition['type'] === 'condition_group') {
                $groupOperator = $condition['operator'] ?? 'AND';
                $result        = $this->evaluateConditions($artifact, $condition['conditions'], $groupOperator);
                $results[]     = $result;
                static::logDebug('Condition group evaluated as: ' . ($result ? 'true' : 'false'));
            }
        }

        // Combine the results using the specified operator
        $finalResult = ($operator === 'AND') ?
            (!empty($results) && !in_array(false, $results)) :
            (!empty($results) && in_array(true, $results));

        static::logDebug("Final result for artifact $artifact->id: " . ($finalResult ? 'true' : 'false'));

        return $finalResult;
    }

    /**
     * Get value to evaluate from an artifact
     *
     * @param  Artifact  $artifact  The artifact to get the value from
     * @param  string  $field  The field to get
     * @param  array|null  $fragmentSelector  Optional fragment selector to extract specific data
     * @return mixed The value from the artifact
     */
    protected function getFieldValue(Artifact $artifact, string $field, ?array $fragmentSelector = null): mixed
    {
        // Support for specific artifact fields
        if ($field === 'text_content') {
            return $artifact->text_content;
        } elseif ($field === 'json_content') {
            return $artifact->getJsonFragmentValue($fragmentSelector);
        } elseif ($field === 'meta') {
            return $artifact->getMetaFragmentValue($fragmentSelector);
        } elseif ($field === 'storedFiles') {
            return $artifact->storedFiles();
        }

        // Check for attribute accessor
        if (method_exists($artifact, $field)) {
            return $artifact->{$field}();
        }

        // Generic attribute
        return $artifact->{$field} ?? null;
    }

    /**
     * Validate the task configuration
     *
     * @param  array  $config  Task configuration
     *
     * @throws ValidationError if the configuration is invalid
     */
    public function validateConfig(array $config): void
    {
        static::logDebug('Validating filter config');

        if (!isset($config['conditions'])) {
            throw new ValidationError("Filter config must have a 'conditions' property");
        }

        if (!is_array($config['conditions'])) {
            throw new ValidationError("Filter config 'conditions' must be an array");
        }

        // Skip validation for empty conditions (they will be handled in the run method)
        if (empty($config['conditions'])) {
            static::logDebug('Empty conditions array detected, validation skipped');

            return;
        }

        // Validate each condition or condition group
        foreach ($config['conditions'] as $condition) {
            if (!isset($condition['type'])) {
                throw new ValidationError("Each condition must have a 'type' property");
            }

            if ($condition['type'] === 'condition') {
                $this->filterService->validateCondition($condition);
            } elseif ($condition['type'] === 'condition_group') {
                $this->validateConditionGroup($condition);
            } else {
                throw new ValidationError('Condition type must be one of: condition, condition_group');
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

        static::logDebug('Filter config validated successfully');
    }

    /**
     * Validate a condition group
     *
     * @param  array  $group  The condition group to validate
     *
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
        foreach ($group['conditions'] as $condition) {
            if (!isset($condition['type'])) {
                throw new ValidationError("Each condition in group must have a 'type' property");
            }

            if ($condition['type'] === 'condition') {
                $this->filterService->validateCondition($condition);
            } elseif ($condition['type'] === 'condition_group') {
                $this->validateConditionGroup($condition);
            } else {
                throw new ValidationError('Condition type in group must be one of: condition, condition_group');
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
     * @param  array  $config  Task configuration
     */
    public function configure(array $config): void
    {
        static::logDebug('Configuring filter task runner');

        $this->keep = ($config['action'] ?? 'keep') === 'keep';

        static::logDebug('Filter task runner configured to ' . ($this->keep ? 'keep' : 'discard') . ' matching artifacts');
    }
}
