<?php

namespace App\Services;

use Newms87\Danx\Exceptions\ValidationError;
use Illuminate\Support\Collection;

class FilterService
{
    /**
     * Evaluate a condition against a data record
     *
     * @param mixed $fieldValue   The field value to evaluate the condition against
     * @param array $condition   The condition to evaluate
     * @return bool Whether the data matches the condition
     */
    public function evaluateCondition(mixed $fieldValue, array $condition): bool
    {
        $operator = $condition['operator'] ?? 'contains';
        $value = $condition['value'] ?? null;
        $caseSensitive = $condition['case_sensitive'] ?? false;

        // Special handling for boolean operators
        if ($operator === 'is_true' || $operator === 'is_false') {
            // Extract boolean value from array if possible
            $booleanValue = null;
            
            // If we have a JSON fragment that contains a boolean field, extract just that field
            if (is_array($fieldValue) && count($fieldValue) === 1) {
                $keys = array_keys($fieldValue);
                $firstKey = reset($keys);
                if (isset($fieldValue[$firstKey]) && is_bool($fieldValue[$firstKey])) {
                    $booleanValue = $fieldValue[$firstKey];
                }
            } elseif (is_bool($fieldValue)) {
                $booleanValue = $fieldValue;
            } elseif (is_scalar($fieldValue) && ($fieldValue === '1' || $fieldValue === '0' || $fieldValue === 1 || $fieldValue === 0)) {
                // Handle numeric/string representations of booleans
                $booleanValue = (bool)$fieldValue;
            }
            
            // Only evaluate if we have an actual boolean value
            if ($booleanValue !== null) {
                if ($operator === 'is_true') {
                    return $booleanValue === true;
                } else { // is_false
                    return $booleanValue === false;
                }
            }
            
            // If we couldn't extract a boolean value, the condition fails
            return false;
        }

        // For 'exists' operator, we need special handling
        if ($operator === 'exists') {
            // If it's null, it doesn't exist
            if ($fieldValue === null) {
                return false;
            }
            
            // If we're checking existence with a fragment selector, 
            // we need to verify the specified field exists in the returned data
            if (isset($condition['fragment_selector']) && is_array($fieldValue)) {
                // For fragment selections, make sure the key specified exists and has a non-null value
                // Extract the field we're checking for existence from the fragment selector
                $keys = array_keys($condition['fragment_selector']['children'] ?? []);
                if (!empty($keys)) {
                    $targetKey = $keys[0];
                    
                    // If we have a field value that's the result of a fragment selection,
                    // we need to check if the specific key is non-empty in the result
                    if (isset($fieldValue[$targetKey]) && $fieldValue[$targetKey] !== null) {
                        return true;
                    }
                    return false;
                }
            }
            
            // For simple field existence check
            return true;
        }

        // If the field value is null, it can't match any value-based condition
        if ($fieldValue === null) {
            return false;
        }

        // Handle collection types
        if ($fieldValue instanceof Collection) {
            return $this->evaluateCollectionCondition($fieldValue, $operator, $value);
        }

        // Handle array values
        if (is_array($fieldValue)) {
            if (empty($fieldValue)) {
                return false;
            }

            // Extract data type from condition for type-specific handling
            $dataType = 'unknown';
            if (isset($condition['fragment_selector'])) {
                $dataType = $this->getDataTypeFromFragmentSelector($condition['fragment_selector']);
            }

            // For flat arrays, try to match any element
            if ($this->isScalarArray($fieldValue)) {
                return $this->evaluateScalarArrayCondition($fieldValue, $value, $operator, $caseSensitive);
            }
            
            // Try to extract leaf value for comparison if possible
            $leafValue = $this->extractLeafValue($fieldValue);
            if ($leafValue !== null) {
                return $this->evaluateTypedCondition($leafValue, $value, $operator, $dataType, $caseSensitive);
            }
            
            // For complex arrays, convert to string for basic operations
            if (in_array($operator, ['contains', 'equals', 'regex'])) {
                $stringValue = json_encode($fieldValue);
                return $this->compareScalarValues($stringValue, $value, $operator, $caseSensitive);
            }

            return false;
        }

        // Determine data type from condition for scalar values
        $dataType = 'string';
        if (is_bool($fieldValue)) {
            $dataType = 'boolean';
        } elseif (is_numeric($fieldValue)) {
            $dataType = 'number';
        }

        // Standard scalar comparison
        return $this->evaluateTypedCondition($fieldValue, $value, $operator, $dataType, $caseSensitive);
    }

    /**
     * Validate a condition structure
     *
     * @param array $condition Condition to validate
     * @throws ValidationError if the condition is invalid
     */
    public function validateCondition(array $condition): void
    {
        if (!isset($condition['field'])) {
            throw new ValidationError("Filter condition must have a 'field' attribute");
        }

        // Validate operator
        $operator = $condition['operator'] ?? 'contains';
        $validOperators = [
            'contains', 'equals', 'greater_than', 'less_than', 'regex', 'exists',
            'is_true', 'is_false' // New boolean-specific operators
        ];
        if (!in_array($operator, $validOperators)) {
            throw new ValidationError("Filter operator '$operator' is not valid. Must be one of: " . implode(', ', $validOperators));
        }

        // Boolean operators and 'exists' don't need a value
        $noValueOperators = ['exists', 'is_true', 'is_false'];
        if (!in_array($operator, $noValueOperators) && !array_key_exists('value', $condition)) {
            throw new ValidationError("Filter condition with operator '$operator' must have a 'value' attribute");
        }
    }

    /**
     * Get the field value from the data
     *
     * @param array|object $data            The data to get the field value from
     * @param string       $field           The field to get the value from
     * @param array|null   $fragmentSelector The fragment selector to apply
     * @return mixed The field value
     */
    protected function getFieldValue(mixed $data, string $field, ?array $fragmentSelector): mixed
    {
        if (is_array($data)) {
            return $data[$field] ?? null;
        }
        
        if (is_object($data)) {
            // Implement as needed for your specific objects
            return $data->{$field} ?? null;
        }
        
        return null;
    }

    /**
     * Evaluate a condition on a collection
     *
     * @param Collection $collection The collection to evaluate
     * @param string     $operator   The operator to use
     * @param mixed      $value      The value to compare against
     * @return bool Whether the collection matches the condition
     */
    protected function evaluateCollectionCondition(Collection $collection, string $operator, mixed $value): bool
    {
        switch ($operator) {
            case 'equals':
                return $collection->count() === (int)$value;
            case 'greater_than':
                return $collection->count() > (int)$value;
            case 'less_than':
                return $collection->count() < (int)$value;
            case 'contains':
                return $collection->contains(function ($item) use ($value) {
                    if (is_scalar($item)) {
                        return (string)$item === (string)$value;
                    }
                    return false;
                });
            default:
                return false;
        }
    }

    /**
     * Evaluate a typed condition based on data type
     *
     * @param mixed  $fieldValue    The field value to compare
     * @param mixed  $conditionValue The condition value to compare against
     * @param string $operator      The operator to use
     * @param string $dataType      The data type of the field
     * @param bool   $caseSensitive Whether to do case-sensitive comparison
     * @return bool Whether the condition is met
     */
    protected function evaluateTypedCondition(mixed $fieldValue, mixed $conditionValue, string $operator, string $dataType, bool $caseSensitive): bool
    {
        switch ($dataType) {
            case 'boolean':
                return $this->evaluateBooleanCondition($fieldValue, $conditionValue, $operator);
            case 'date':
                return $this->evaluateDateCondition($fieldValue, $conditionValue, $operator);
            case 'number':
                return $this->evaluateNumberCondition($fieldValue, $conditionValue, $operator);
            case 'string':
                return $this->evaluateStringCondition($fieldValue, $conditionValue, $operator, $caseSensitive);
            case 'array':
                if (is_array($fieldValue)) {
                    return $this->evaluateScalarArrayCondition($fieldValue, $conditionValue, $operator, $caseSensitive);
                }
                return false;
            default:
                return $this->compareScalarValues($fieldValue, $conditionValue, $operator, $caseSensitive);
        }
    }

    /**
     * Evaluate a boolean condition
     *
     * @param mixed  $fieldValue    The field value to compare
     * @param mixed  $conditionValue The condition value to compare against
     * @param string $operator      The operator to use
     * @return bool Whether the condition is met
     */
    protected function evaluateBooleanCondition(mixed $fieldValue, mixed $conditionValue, string $operator): bool
    {
        // Convert string value to boolean if needed
        if (is_string($conditionValue) && strtolower($conditionValue) === 'true') {
            $conditionValue = true;
        } elseif (is_string($conditionValue) && strtolower($conditionValue) === 'false') {
            $conditionValue = false;
        }
        
        if (is_string($fieldValue) && strtolower($fieldValue) === 'true') {
            $fieldValue = true;
        } elseif (is_string($fieldValue) && strtolower($fieldValue) === 'false') {
            $fieldValue = false;
        }
        
        // Only equals operator is valid for booleans
        if ($operator === 'equals') {
            return (bool)$fieldValue === (bool)$conditionValue;
        }
        
        // New boolean-specific operators
        if ($operator === 'is_true') {
            return (bool)$fieldValue === true;
        }
        if ($operator === 'is_false') {
            return (bool)$fieldValue === false;
        }
        
        return false;
    }

    /**
     * Evaluate a date condition
     *
     * @param mixed  $fieldValue    The field value to compare
     * @param mixed  $conditionValue The condition value to compare against
     * @param string $operator      The operator to use
     * @return bool Whether the condition is met
     */
    protected function evaluateDateCondition(mixed $fieldValue, mixed $conditionValue, string $operator): bool
    {
        // Convert string dates to timestamps for comparison
        $dateValue = is_string($fieldValue) ? strtotime($fieldValue) : null;
        $dateConditionValue = is_string($conditionValue) ? strtotime($conditionValue) : null;
        
        if ($dateValue === false || $dateConditionValue === false) {
            return false;
        }
        
        switch ($operator) {
            case 'equals':
                return $dateValue === $dateConditionValue;
            case 'greater_than':
                return $dateValue > $dateConditionValue;
            case 'less_than':
                return $dateValue < $dateConditionValue;
            default:
                return false;
        }
    }

    /**
     * Evaluate a number condition
     *
     * @param mixed  $fieldValue    The field value to compare
     * @param mixed  $conditionValue The condition value to compare against
     * @param string $operator      The operator to use
     * @return bool Whether the condition is met
     */
    protected function evaluateNumberCondition(mixed $fieldValue, mixed $conditionValue, string $operator): bool
    {
        // Convert to numeric for comparison
        $numValue = is_numeric($fieldValue) ? (float)$fieldValue : null;
        $numConditionValue = is_numeric($conditionValue) ? (float)$conditionValue : null;
        
        if ($numValue === null || $numConditionValue === null) {
            return false;
        }
        
        switch ($operator) {
            case 'equals':
                return $numValue === $numConditionValue;
            case 'greater_than':
                return $numValue > $numConditionValue;
            case 'less_than':
                return $numValue < $numConditionValue;
            default:
                return false;
        }
    }

    /**
     * Evaluate a string condition
     *
     * @param mixed  $fieldValue    The field value to compare
     * @param mixed  $conditionValue The condition value to compare against
     * @param string $operator      The operator to use
     * @param bool   $caseSensitive Whether to do case-sensitive comparison
     * @return bool Whether the condition is met
     */
    protected function evaluateStringCondition(mixed $fieldValue, mixed $conditionValue, string $operator, bool $caseSensitive): bool
    {
        if (!is_scalar($fieldValue) || !is_scalar($conditionValue)) {
            return false;
        }
        
        $fieldStr = (string)$fieldValue;
        $conditionStr = (string)$conditionValue;
        
        // Only apply case conversion for non-regex operations or when specified for regex
        if (!$caseSensitive && $operator !== 'regex') {
            $fieldStr = strtolower($fieldStr);
            $conditionStr = strtolower($conditionStr);
        }
        
        switch ($operator) {
            case 'contains':
                return strpos($fieldStr, $conditionStr) !== false;
            case 'equals':
                return $fieldStr === $conditionStr;
            case 'regex':
                // For regex, don't add extra / if the pattern already includes them
                if (substr($conditionStr, 0, 1) === '/' && substr($conditionStr, -1) === '/') {
                    // Pattern already has delimiters
                    $pattern = $conditionStr;
                    
                    // Add modifiers for case insensitivity if needed
                    if (!$caseSensitive && strpos($pattern, 'i') === false) {
                        // Add 'i' modifier for case insensitive matching
                        $pattern = $pattern . 'i';
                    }
                } else {
                    // Add delimiters and modifiers
                    $pattern = '/' . str_replace('/', '\/', $conditionStr) . '/' . (!$caseSensitive ? 'i' : '');
                }
                
                // Use error suppression to prevent warnings from malformed regex
                $result = @preg_match($pattern, $fieldStr);
                return $result === 1;
            case 'greater_than':
                return $fieldStr > $conditionStr; // Lexicographical comparison
            case 'less_than':
                return $fieldStr < $conditionStr; // Lexicographical comparison
            default:
                return false;
        }
    }

    /**
     * Evaluate a scalar array condition
     *
     * @param array  $arrayValue    The array to evaluate
     * @param mixed  $conditionValue The condition value to compare against
     * @param string $operator      The operator to use
     * @param bool   $caseSensitive Whether to do case-sensitive comparison
     * @return bool Whether the condition is met
     */
    protected function evaluateScalarArrayCondition(array $arrayValue, mixed $conditionValue, string $operator, bool $caseSensitive): bool
    {
        foreach ($arrayValue as $item) {
            if ($this->compareScalarValues($item, $conditionValue, $operator, $caseSensitive)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Compare scalar values
     *
     * @param mixed  $fieldValue    The field value to compare
     * @param mixed  $conditionValue The condition value to compare against
     * @param string $operator      The operator to use
     * @param bool   $caseSensitive Whether to do case-sensitive comparison
     * @return bool Whether the condition is met
     */
    protected function compareScalarValues(mixed $fieldValue, mixed $conditionValue, string $operator, bool $caseSensitive): bool
    {
        if (!is_scalar($fieldValue) || !is_scalar($conditionValue)) {
            return false;
        }
        
        $fieldStr = (string)$fieldValue;
        $conditionStr = (string)$conditionValue;
        
        if (!$caseSensitive) {
            $fieldStr = strtolower($fieldStr);
            $conditionStr = strtolower($conditionStr);
        }
        
        switch ($operator) {
            case 'contains':
                return strpos($fieldStr, $conditionStr) !== false;
            case 'equals':
                return $fieldStr === $conditionStr;
            case 'greater_than':
                if (is_numeric($fieldValue) && is_numeric($conditionValue)) {
                    return (float)$fieldValue > (float)$conditionValue;
                }
                return $fieldStr > $conditionStr; // Lexicographical comparison
            case 'less_than':
                if (is_numeric($fieldValue) && is_numeric($conditionValue)) {
                    return (float)$fieldValue < (float)$conditionValue;
                }
                return $fieldStr < $conditionStr; // Lexicographical comparison
            case 'regex':
                return @preg_match('/' . str_replace('/', '\/', $conditionStr) . '/m', $fieldStr) === 1;
            default:
                return false;
        }
    }

    /**
     * Extract the leaf value from a nested array structure
     *
     * @param array $data The data structure
     * @return mixed|null The leaf value or null if not found
     */
    protected function extractLeafValue(array $data): mixed
    {
        if (empty($data)) {
            return null;
        }
        
        // If there's only one key at this level, go deeper
        if (count($data) === 1) {
            $key = array_key_first($data);
            $value = $data[$key];
            
            if (is_array($value)) {
                // If this is an empty array or indexed array, return it as is
                if (empty($value) || isset($value[0])) {
                    return $value;
                }
                // Otherwise, try to extract deeper leaf value
                return $this->extractLeafValue($value);
            }
            
            // If the value is scalar, we found our leaf
            if (is_scalar($value) || is_null($value)) {
                return $value;
            }
        }
        
        // If we have multiple keys or the value isn't what we expected, return the array as is
        return $data;
    }

    /**
     * Check if an array contains only scalar values
     *
     * @param array $array The array to check
     * @return bool Whether the array contains only scalar values
     */
    protected function isScalarArray(array $array): bool
    {
        foreach ($array as $value) {
            if (!is_scalar($value)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Get available operators for a specific data type
     *
     * @param string $dataType The data type
     * @return array Array of valid operators
     */
    public function getOperatorsForDataType(string $dataType): array
    {
        switch ($dataType) {
            case 'boolean':
                return ['equals', 'exists', 'is_true', 'is_false'];

            case 'number':
                return ['equals', 'greater_than', 'less_than', 'exists'];

            case 'date':
                return ['equals', 'greater_than', 'less_than', 'exists'];

            case 'string':
                // Added greater_than and less_than for string lexicographical comparison
                return ['contains', 'equals', 'greater_than', 'less_than', 'regex', 'exists'];

            case 'array':
                return ['contains', 'exists'];

            case 'unknown':
                throw new ValidationError("Unknown data type cannot be filtered");

            default:
                return ['contains', 'equals', 'greater_than', 'less_than', 'regex', 'exists'];
        }
    }

    /**
     * Determine the data type from a fragment selector
     *
     * @param array|null $fragmentSelector The fragment selector
     * @return string The data type ('string', 'boolean', 'number', 'date', 'array', 'object', 'unknown')
     */
    public function getDataTypeFromFragmentSelector(?array $fragmentSelector): string
    {
        if (!$fragmentSelector || empty($fragmentSelector['children'])) {
            return 'unknown';
        }

        // Get the first leaf node from the fragment selector
        $leafNode = $this->findFirstLeafNode($fragmentSelector);
        if (!$leafNode) {
            return 'unknown';
        }

        $type = $leafNode['type'] ?? 'unknown';
        $format = $leafNode['format'] ?? null;

        // Handle date format as a special case
        if ($type === 'string' && $format === 'date') {
            return 'date';
        }

        return $type;
    }

    /**
     * Find the first leaf node in a fragment selector
     *
     * @param array $fragmentSelector The fragment selector
     * @return array|null The leaf node or null if not found
     */
    protected function findFirstLeafNode(array $fragmentSelector): ?array
    {
        if (empty($fragmentSelector['children'])) {
            return null;
        }

        // Get the first child
        $firstChild = reset($fragmentSelector['children']);
        $childKey = key($fragmentSelector['children']);

        // If this child has no children of its own and has a type, it's a leaf node
        if (isset($firstChild['type']) && (!isset($firstChild['children']) || empty($firstChild['children']))) {
            return $firstChild;
        }

        // Otherwise, recursively search for a leaf node
        return $this->findFirstLeafNode($firstChild);
    }
}
