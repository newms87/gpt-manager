<?php

namespace App\Services\Task\Runners;

use App\Models\Task\Artifact;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Newms87\Danx\Exceptions\ValidationError;

/**
 * Filters artifacts based on specified conditions and returns only those that match.
 * 
 * Filter config structure:
 * [
 *   'operator' => 'AND|OR', // Default: 'AND'
 *   'conditions' => [
 *     [
 *       'field' => 'text_content|json_content|meta|storedFiles', // Required
 *       'path' => 'dot.notation.path', // For json_content and meta only, optional
 *       'operator' => 'contains|equals|greater_than|less_than|regex|exists', // Default: 'contains'
 *       'value' => 'value to compare against', // Required unless operator is 'exists'
 *       'case_sensitive' => true|false, // Default: false, only applicable to string comparisons
 *     ],
 *     // More conditions...
 *     // OR nested condition groups
 *     [
 *       'operator' => 'AND|OR', 
 *       'conditions' => [ ... ]
 *     ]
 *   ]
 * ]
 */
class FilterArtifactsTaskRunner extends BaseTaskRunner
{
    const string RUNNER_NAME = 'Filter Artifacts';
    
    /**
     * Run the filter task on the input artifacts
     */
    public function run(): void 
    {
        $this->activity('Filtering artifacts based on content...', 10);
        
        $filterConfig = $this->config('filter_config', []);
        
        if (empty($filterConfig)) {
            $this->activity('No filter config provided, passing all artifacts through', 100);
            $this->complete($this->taskProcess->inputArtifacts);
            return;
        }
        
        // Validate the filter config structure
        $this->validateFilterConfig($filterConfig);
        
        $filteredArtifacts = $this->filterArtifacts($this->taskProcess->inputArtifacts, $filterConfig);
        
        $totalArtifacts = $this->taskProcess->inputArtifacts->count();
        $filteredCount = count($filteredArtifacts);
        
        $this->activity("Filtered $filteredCount out of $totalArtifacts artifacts", 100);
        
        $this->complete($filteredArtifacts);
    }
    
    /**
     * Validate that the filter config has the required structure
     * 
     * @param array $filterConfig The filter configuration
     * @throws ValidationError If the filter config is invalid
     */
    protected function validateFilterConfig(array $filterConfig): void
    {
        if (!isset($filterConfig['conditions']) || !is_array($filterConfig['conditions']) || empty($filterConfig['conditions'])) {
            throw new ValidationError("Filter config must contain a 'conditions' array");
        }
        
        if (isset($filterConfig['operator']) && !in_array(strtoupper($filterConfig['operator']), ['AND', 'OR'])) {
            throw new ValidationError("Filter config 'operator' must be either 'AND' or 'OR'");
        }
        
        $this->validateConditions($filterConfig['conditions']);
    }
    
    /**
     * Recursively validate filter conditions
     * 
     * @param array $conditions The filter conditions to validate
     * @throws ValidationError If any condition is invalid
     */
    protected function validateConditions(array $conditions): void
    {
        foreach ($conditions as $condition) {
            // Check if this is a nested condition group
            if (isset($condition['operator']) && isset($condition['conditions'])) {
                if (!in_array(strtoupper($condition['operator']), ['AND', 'OR'])) {
                    throw new ValidationError("Nested condition group 'operator' must be either 'AND' or 'OR'");
                }
                
                $this->validateConditions($condition['conditions']);
                continue;
            }
            
            // Validate leaf condition
            if (!isset($condition['field'])) {
                throw new ValidationError("Each filter condition must have a 'field' property");
            }
            
            if (!in_array($condition['field'], ['text_content', 'json_content', 'meta', 'storedFiles'])) {
                throw new ValidationError("Filter condition 'field' must be one of: text_content, json_content, meta, storedFiles");
            }
            
            $operator = $condition['operator'] ?? 'contains';
            if (!in_array($operator, ['contains', 'equals', 'greater_than', 'less_than', 'regex', 'exists'])) {
                throw new ValidationError("Filter condition 'operator' must be one of: contains, equals, greater_than, less_than, regex, exists");
            }
            
            // Check if value is required
            if ($operator !== 'exists' && !isset($condition['value'])) {
                throw new ValidationError("Filter condition with operator '$operator' must have a 'value' property");
            }
        }
    }
    
    /**
     * Filter artifacts based on the provided filter configuration
     * 
     * @param \Illuminate\Database\Eloquent\Collection $artifacts The artifacts to filter
     * @param array $filterConfig The filter configuration
     * @return array Filtered artifacts
     */
    protected function filterArtifacts($artifacts, array $filterConfig): array
    {
        $filteredArtifacts = [];
        $operator = strtoupper($filterConfig['operator'] ?? 'AND');
        
        foreach ($artifacts as $artifact) {
            $matches = $this->evaluateConditionGroup($artifact, $filterConfig['conditions'], $operator);
            
            if ($matches) {
                $filteredArtifacts[] = $artifact;
            }
        }
        
        return $filteredArtifacts;
    }
    
    /**
     * Evaluate a group of conditions against an artifact
     * 
     * @param Artifact $artifact The artifact to evaluate
     * @param array $conditions The conditions to evaluate
     * @param string $groupOperator The operator to use when combining conditions ('AND' or 'OR')
     * @return bool Whether the artifact matches the conditions
     */
    protected function evaluateConditionGroup(Artifact $artifact, array $conditions, string $groupOperator): bool
    {
        $groupOperator = strtoupper($groupOperator);
        
        // Default result based on operator
        $matches = ($groupOperator === 'AND');
        
        foreach ($conditions as $condition) {
            // Check if this is a nested condition group
            if (isset($condition['operator']) && isset($condition['conditions'])) {
                $nestedResult = $this->evaluateConditionGroup(
                    $artifact, 
                    $condition['conditions'], 
                    $condition['operator']
                );
                
                // Apply the result based on the group operator
                if ($groupOperator === 'AND') {
                    $matches = $matches && $nestedResult;
                    // Short circuit if any condition fails in an AND group
                    if (!$matches) {
                        break;
                    }
                } else {
                    $matches = $matches || $nestedResult;
                    // Short circuit if any condition passes in an OR group
                    if ($matches) {
                        break;
                    }
                }
                
                continue;
            }
            
            // Evaluate leaf condition
            $conditionResult = $this->evaluateCondition($artifact, $condition);
            
            // Apply the result based on the group operator
            if ($groupOperator === 'AND') {
                $matches = $matches && $conditionResult;
                // Short circuit if any condition fails in an AND group
                if (!$matches) {
                    break;
                }
            } else {
                $matches = $matches || $conditionResult;
                // Short circuit if any condition passes in an OR group
                if ($matches) {
                    break;
                }
            }
        }
        
        return $matches;
    }
    
    /**
     * Evaluate a single condition against an artifact
     * 
     * @param Artifact $artifact The artifact to evaluate
     * @param array $condition The condition to evaluate
     * @return bool Whether the artifact matches the condition
     */
    protected function evaluateCondition(Artifact $artifact, array $condition): bool
    {
        $field = $condition['field'];
        $operator = $condition['operator'] ?? 'contains';
        $path = $condition['path'] ?? null;
        
        static::log("Evaluating condition on field: $field" . ($path ? " path: $path" : '') . " operator: $operator");
        
        // Handle stored files separately since they're a relation
        if ($field === 'storedFiles') {
            return $this->evaluateStoredFilesCondition($artifact, $condition);
        }
        
        // Get the field value based on the path if specified
        $value = $this->getFieldValue($artifact, $field, $path);
        
        // Check if the field exists
        if ($operator === 'exists') {
            $result = $value !== null;
            static::log("Exists check result: " . ($result ? 'true' : 'false'));
            return $result;
        }
        
        // If value is null and we're not checking for existence, condition fails
        if ($value === null) {
            static::log("Value is null, condition fails");
            return false;
        }
        
        $conditionValue = $condition['value'];
        $caseSensitive = $condition['case_sensitive'] ?? false;
        
        $result = $this->compareValues($value, $conditionValue, $operator, $caseSensitive);
        static::log("Comparison result: " . ($result ? 'true' : 'false') . 
            " Value: " . (is_array($value) ? json_encode($value) : (string)$value) . 
            " Condition value: " . (is_array($conditionValue) ? json_encode($conditionValue) : (string)$conditionValue));
        
        return $result;
    }
    
    /**
     * Evaluate a condition against stored files
     * 
     * @param Artifact $artifact The artifact to evaluate
     * @param array $condition The condition to evaluate
     * @return bool Whether the artifact's stored files match the condition
     */
    protected function evaluateStoredFilesCondition(Artifact $artifact, array $condition): bool
    {
        $operator = $condition['operator'] ?? 'exists';
        
        // Check if artifact has any stored files
        if ($operator === 'exists') {
            return $artifact->storedFiles->isNotEmpty();
        }
        
        // For other operators, we check if any file matches the condition
        $conditionValue = $condition['value'];
        $caseSensitive = $condition['case_sensitive'] ?? false;
        $path = $condition['path'] ?? 'filename'; // Default to filename
        
        foreach ($artifact->storedFiles as $file) {
            $fileValue = Arr::get($file->toArray(), $path);
            
            if ($fileValue !== null && $this->compareValues($fileValue, $conditionValue, $operator, $caseSensitive)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get a value from an artifact's field
     * 
     * @param Artifact $artifact The artifact to get the value from
     * @param string $field The field to get the value from
     * @param string|null $path Optional dot notation path for json_content and meta fields
     * @return mixed The value from the artifact's field
     */
    protected function getFieldValue(Artifact $artifact, string $field, ?string $path = null): mixed
    {
        if ($field === 'text_content') {
            return $artifact->text_content;
        }
        
        if ($field === 'json_content' || $field === 'meta') {
            $data = $field === 'json_content' ? ($artifact->json_content ?? []) : ($artifact->meta ?? []);
            
            if ($path) {
                return Arr::get($data, $path);
            }
            
            return $data;
        }
        
        return null;
    }
    
    /**
     * Compare two values based on the specified operator
     * 
     * @param mixed $value The value from the artifact
     * @param mixed $conditionValue The value from the condition
     * @param string $operator The operator to use for comparison
     * @param bool $caseSensitive Whether string comparisons should be case sensitive
     * @return bool Whether the comparison is true
     */
    protected function compareValues($value, $conditionValue, string $operator, bool $caseSensitive): bool
    {
        // Handle scalar vs array values
        if (is_array($value)) {
            // For arrays, check if any element matches the condition
            foreach ($value as $element) {
                if ($this->compareScalarValues($element, $conditionValue, $operator, $caseSensitive)) {
                    return true;
                }
            }
            return false;
        }
        
        return $this->compareScalarValues($value, $conditionValue, $operator, $caseSensitive);
    }
    
    /**
     * Compare scalar values based on the specified operator
     * 
     * @param mixed $value The value from the artifact
     * @param mixed $conditionValue The value from the condition
     * @param string $operator The operator to use for comparison
     * @param bool $caseSensitive Whether string comparisons should be case sensitive
     * @return bool Whether the comparison is true
     */
    protected function compareScalarValues($value, $conditionValue, string $operator, bool $caseSensitive): bool
    {
        // Convert strings for case-insensitive comparison
        if (is_string($value) && is_string($conditionValue) && !$caseSensitive) {
            $value = Str::lower($value);
            $conditionValue = Str::lower($conditionValue);
        }
        
        switch ($operator) {
            case 'contains':
                return is_string($value) && is_string($conditionValue) && Str::contains($value, $conditionValue);
                
            case 'equals':
                return $value === $conditionValue;
                
            case 'greater_than':
                return $value > $conditionValue;
                
            case 'less_than':
                return $value < $conditionValue;
                
            case 'regex':
                return is_string($value) && preg_match($conditionValue, $value);
                
            default:
                return false;
        }
    }
}
