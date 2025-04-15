<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Newms87\Danx\Exceptions\ValidationError;

class FilterService
{
    // Common operator constants
    private const OPERATOR_EQUALS = 'equals';
    private const OPERATOR_CONTAINS = 'contains';
    private const OPERATOR_GREATER_THAN = 'greater_than';
    private const OPERATOR_LESS_THAN = 'less_than';
    private const OPERATOR_REGEX = 'regex';
    private const OPERATOR_EXISTS = 'exists';
    private const OPERATOR_IS_TRUE = 'is_true';
    private const OPERATOR_IS_FALSE = 'is_false';
    
    /**
     * Evaluate a condition against a data record
     *
     * @param mixed $fieldValue The field value to evaluate the condition against
     * @param array $condition  The condition to evaluate
     * @return bool Whether the data matches the condition
     */
    public function evaluateCondition(mixed $fieldValue, array $condition): bool
    {
        $operator      = $condition['operator'] ?? self::OPERATOR_CONTAINS;
        $value         = $condition['value'] ?? null;
        $caseSensitive = $condition['case_sensitive'] ?? false;

        // Handle special boolean operators
        if ($this->isBooleanOperator($operator)) {
            return $this->evaluateBooleanOperator($fieldValue, $operator);
        }

        // Handle exists operator
        if ($operator === self::OPERATOR_EXISTS) {
            return $this->evaluateExistsOperator($fieldValue, $condition);
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
            return $this->evaluateArrayCondition($fieldValue, $condition, $operator, $value, $caseSensitive); 
        }

        // Determine data type for scalar values
        $dataType = $this->determineScalarDataType($fieldValue, $condition);

        // Standard scalar comparison
        return $this->evaluateTypedCondition($fieldValue, $value, $operator, $dataType, $caseSensitive);
    }

    /**
     * Determine the data type for a scalar value
     * 
     * @param mixed $fieldValue The field value
     * @param array $condition The condition being evaluated
     * @return string The data type
     */
    protected function determineScalarDataType(mixed $fieldValue, array $condition): string
    {
        // Check for numeric values that should be treated as booleans
        if (is_numeric($fieldValue) && ($fieldValue === 1 || $fieldValue === 0) && isset($condition['fragment_selector'])) {
            $selectorDataType = $this->getDataTypeFromFragmentSelector($condition['fragment_selector']);
            if ($selectorDataType === 'boolean') {
                return 'boolean';
            }
        }
        
        if (is_bool($fieldValue)) {
            return 'boolean';
        } elseif (is_numeric($fieldValue)) {
            return 'number';
        }
        
        return 'string';
    }

    /**
     * Evaluate a boolean operator (is_true, is_false)
     * 
     * @param mixed $fieldValue The field value
     * @param string $operator The operator (is_true or is_false)
     * @return bool The evaluation result
     */
    protected function evaluateBooleanOperator(mixed $fieldValue, string $operator): bool
    {
        $booleanValue = $this->extractBooleanValue($fieldValue);
        
        // Only evaluate if we have an actual boolean value
        if ($booleanValue !== null) {
            if ($operator === self::OPERATOR_IS_TRUE) {
                return $booleanValue === true;
            } else { // is_false
                return $booleanValue === false;
            }
        }

        // If we couldn't extract a boolean value, the condition fails
        return false;
    }

    /**
     * Extract a boolean value from various formats
     * 
     * @param mixed $value The value to extract from
     * @return bool|null The extracted boolean or null if extraction failed
     */
    protected function extractBooleanValue(mixed $value): ?bool
    {
        // If we have a JSON fragment that contains a boolean field, extract just that field
        if (is_array($value) && count($value) === 1) {
            $keys     = array_keys($value);
            $firstKey = reset($keys);
            if (isset($value[$firstKey]) && is_bool($value[$firstKey])) {
                return $value[$firstKey];
            }
        } elseif (is_bool($value)) {
            return $value;
        } elseif (is_scalar($value)) {
            // Handle string and numeric representations of booleans
            if (is_string($value)) {
                if (strtolower($value) === 'true') {
                    return true;
                } elseif (strtolower($value) === 'false') {
                    return false;
                }
            }
            
            // Handle numeric/string 1/0 values
            if ($value === '1' || $value === '0' || $value === 1 || $value === 0) {
                return (bool)$value;
            }
        }
        
        return null;
    }

    /**
     * Check if an operator is a boolean operator
     * 
     * @param string $operator The operator to check
     * @return bool Whether it's a boolean operator
     */
    protected function isBooleanOperator(string $operator): bool
    {
        return $operator === self::OPERATOR_IS_TRUE || $operator === self::OPERATOR_IS_FALSE;
    }

    /**
     * Evaluate the 'exists' operator against a field value
     * 
     * @param mixed $fieldValue The field value to check
     * @param array $condition The full condition with possible fragment selector
     * @return bool Whether the field exists
     */
    protected function evaluateExistsOperator(mixed $fieldValue, array $condition): bool
    {
        // If it's null, it doesn't exist
        if ($fieldValue === null) {
            return false;
        }

        // If we're checking existence with a fragment selector,
        // we need to verify the specified field exists in the returned data
        if (isset($condition['fragment_selector']) && is_array($fieldValue)) {
            return $this->checkFragmentExists($fieldValue, $condition['fragment_selector']);
        }

        // For simple field existence check
        return true;
    }

    /**
     * Check if a fragment exists in a field value
     * 
     * @param array $fieldValue The array field value
     * @param array $fragmentSelector The fragment selector
     * @return bool Whether the fragment exists
     */
    protected function checkFragmentExists(array $fieldValue, array $fragmentSelector): bool
    {
        // For fragment selections, make sure the key specified exists and has a non-null value
        // Extract the field we're checking for existence from the fragment selector
        $keys = array_keys($fragmentSelector['children'] ?? []);
        if (!empty($keys)) {
            $targetKey = $keys[0];

            // If we have a field value that's the result of a fragment selection,
            // we need to check if the specific key is non-empty in the result
            return isset($fieldValue[$targetKey]) && $fieldValue[$targetKey] !== null;
        }

        return false;
    }

    /**
     * Evaluate a condition on array values
     * 
     * @param array $fieldValue The array to evaluate
     * @param array $condition The full condition
     * @param string $operator The operator to use
     * @param mixed $value The value to compare against
     * @param bool $caseSensitive Whether to use case-sensitive comparison
     * @return bool Whether the condition is met
     */
    protected function evaluateArrayCondition(array $fieldValue, array $condition, string $operator, mixed $value, bool $caseSensitive): bool
    {
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
        if (in_array($operator, [self::OPERATOR_CONTAINS, self::OPERATOR_EQUALS, self::OPERATOR_REGEX])) {
            $stringValue = json_encode($fieldValue);

            return $this->compareScalarValues($stringValue, $value, $operator, $caseSensitive);
        }

        return false;
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
            throw new ValidationError("Condition must have a 'field' property");
        }

        if (!isset($condition['operator'])) {
            throw new ValidationError("Condition must have an 'operator' property");
        }

        // 'exists' operator doesn't require a value
        if ($condition['operator'] !== self::OPERATOR_EXISTS && 
            $condition['operator'] !== self::OPERATOR_IS_TRUE && 
            $condition['operator'] !== self::OPERATOR_IS_FALSE && 
            !isset($condition['value'])) {
            throw new ValidationError("Condition must have a 'value' property unless operator is 'exists', 'is_true', or 'is_false'");
        }

        // Check if operator is valid
        $dataType = 'unknown';
        if (isset($condition['fragment_selector'])) {
            $dataType = $this->getDataTypeFromFragmentSelector($condition['fragment_selector']);
        }

        $validOperators = $this->getOperatorsForDataType($dataType);
        if (!in_array($condition['operator'], $validOperators)) {
            throw new ValidationError("Operator '{$condition['operator']}' is not valid for data type '{$dataType}'");
        }
    }

    /**
     * Get the field value from the data
     *
     * @param array|object $data             The data to get the field value from
     * @param string       $field            The field to get the value from
     * @param array|null   $fragmentSelector The fragment selector to apply
     * @return mixed The field value
     */
    public function getFieldValue(mixed $data, string $field, ?array $fragmentSelector): mixed
    {
        if (is_array($data)) {
            $value = $data[$field] ?? null;
        } elseif (is_object($data)) {
            $value = $data->{$field} ?? null;
        } else {
            $value = null;
        }

        // Apply fragment selector if provided and the value is an array
        if ($fragmentSelector && is_array($value)) {
            // Fragment selector allows extracting a specific property from a nested structure
            // For example, json_content.category
            // The property is selected via the schema-based fragment selector
            return [$fragmentSelector['children'] ? array_key_first($fragmentSelector['children']) : 'value' => $value];
        }

        return $value;
    }

    /**
     * Evaluate a condition on a collection
     *
     * @param Collection $collection The collection to evaluate
     * @param string     $operator   The operator to use
     * @param mixed      $value      The value to compare against
     * @return bool Whether the collection matches the condition
     */
    public function evaluateCollectionCondition(Collection $collection, string $operator, mixed $value): bool
    {
        if ($collection->isEmpty()) {
            return false;
        }

        // Convert to array for consistent handling
        return $this->evaluateArrayCondition($collection->toArray(), [], $operator, $value, false);
    }

    /**
     * Evaluate a typed condition based on data type
     *
     * @param mixed  $fieldValue     The field value to compare
     * @param mixed  $conditionValue The condition value to compare against
     * @param string $operator       The operator to use
     * @param string $dataType       The data type of the field
     * @param bool   $caseSensitive  Whether to do case-sensitive comparison
     * @return bool Whether the condition is met
     */
    protected function evaluateTypedCondition(mixed $fieldValue, mixed $conditionValue, string $operator, string $dataType, bool $caseSensitive): bool
    {
        switch($dataType) {
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
     * @param mixed  $fieldValue     The field value to compare
     * @param mixed  $conditionValue The condition value to compare against
     * @param string $operator       The operator to use
     * @return bool Whether the condition is met
     */
    protected function evaluateBooleanCondition(mixed $fieldValue, mixed $conditionValue, string $operator): bool
    {
        // Convert string and numeric values to booleans for comparison
        $fieldValue = $this->normalizeBooleanValue($fieldValue);
        $conditionValue = $this->normalizeBooleanValue($conditionValue);

        // Only equals operator is valid for booleans
        if ($operator === self::OPERATOR_EQUALS) {
            // Use loose comparison for boolean values to handle mixed data types (like 1 == true)
            return $fieldValue == $conditionValue;
        }

        // Boolean-specific operators
        if ($operator === self::OPERATOR_IS_TRUE) {
            return (bool)$fieldValue === true;
        }
        if ($operator === self::OPERATOR_IS_FALSE) {
            return (bool)$fieldValue === false;
        }

        return false;
    }

    /**
     * Normalize a value to a boolean when possible
     * 
     * @param mixed $value The value to normalize
     * @return mixed The normalized value (bool if possible, original value otherwise)
     */
    protected function normalizeBooleanValue(mixed $value): mixed
    {
        // Convert string value to boolean if needed
        if (is_string($value)) {
            if (strtolower($value) === 'true') {
                return true;
            } elseif (strtolower($value) === 'false') {
                return false;
            }
        }

        // Convert numeric values to booleans
        if (is_numeric($value)) {
            return (bool)$value;
        }

        return $value;
    }

    /**
     * Evaluate a date condition
     *
     * @param mixed  $fieldValue     The field value to compare
     * @param mixed  $conditionValue The condition value to compare against
     * @param string $operator       The operator to use
     * @return bool Whether the condition is met
     */
    protected function evaluateDateCondition(mixed $fieldValue, mixed $conditionValue, string $operator): bool
    {
        // Convert string dates to timestamps for comparison
        $fieldTimestamp = is_string($fieldValue) ? strtotime($fieldValue) : null;
        $valueTimestamp = is_string($conditionValue) ? strtotime($conditionValue) : null;

        if ($fieldTimestamp === false || $valueTimestamp === false) {
            return false; // Invalid date format
        }

        switch($operator) {
            case self::OPERATOR_EQUALS:
                return $fieldTimestamp === $valueTimestamp;
            case self::OPERATOR_GREATER_THAN:
                return $fieldTimestamp > $valueTimestamp;
            case self::OPERATOR_LESS_THAN:
                return $fieldTimestamp < $valueTimestamp;
            default:
                return false;
        }
    }

    /**
     * Evaluate a number condition
     *
     * @param mixed  $fieldValue     The field value to compare
     * @param mixed  $conditionValue The condition value to compare against
     * @param string $operator       The operator to use
     * @return bool Whether the condition is met
     */
    protected function evaluateNumberCondition(mixed $fieldValue, mixed $conditionValue, string $operator): bool
    {
        // Convert to numeric values if strings
        if (is_string($fieldValue) && is_numeric($fieldValue)) {
            $fieldValue = (float)$fieldValue;
        }

        if (is_string($conditionValue) && is_numeric($conditionValue)) {
            $conditionValue = (float)$conditionValue;
        }

        switch($operator) {
            case self::OPERATOR_EQUALS:
                return $fieldValue == $conditionValue; // Use loose comparison
            case self::OPERATOR_GREATER_THAN:
                return $fieldValue > $conditionValue;
            case self::OPERATOR_LESS_THAN:
                return $fieldValue < $conditionValue;
            default:
                return false;
        }
    }

    /**
     * Evaluate a string condition
     *
     * @param mixed  $fieldValue     The field value to compare
     * @param mixed  $conditionValue The condition value to compare against
     * @param string $operator       The operator to use
     * @param bool   $caseSensitive  Whether to do case-sensitive comparison
     * @return bool Whether the condition is met
     */
    protected function evaluateStringCondition(mixed $fieldValue, mixed $conditionValue, string $operator, bool $caseSensitive): bool
    {
        // Ensure we have strings for comparison
        $fieldString = is_scalar($fieldValue) ? (string)$fieldValue : '';
        $valueString = is_scalar($conditionValue) ? (string)$conditionValue : '';

        // For regex, case sensitivity is handled through regex modifiers
        if (!$caseSensitive && $operator !== self::OPERATOR_REGEX) {
            $fieldString  = strtolower($fieldString);
            $valueString = strtolower($valueString);
        }

        switch($operator) {
            case self::OPERATOR_CONTAINS:
                return str_contains($fieldString, $valueString);

            case self::OPERATOR_EQUALS:
                return $fieldString === $valueString;

            case self::OPERATOR_GREATER_THAN:
                return $fieldString > $valueString; // Lexicographical comparison

            case self::OPERATOR_LESS_THAN:
                return $fieldString < $valueString; // Lexicographical comparison

            case self::OPERATOR_REGEX:
                if (empty($valueString)) {
                    return false;
                }
                
                // Determine if the pattern already includes delimiters
                $hasDelimiters = strlen($valueString) > 1 && 
                                 $valueString[0] === '/' && 
                                 substr($valueString, -1) === '/';
                
                // For patterns with existing delimiters, preserve them
                if ($hasDelimiters) {
                    $pattern = $valueString;
                    
                    // If case-insensitive and the pattern doesn't already have 'i' flag, add it
                    if (!$caseSensitive) {
                        $lastSlashPos = strrpos($pattern, '/');
                        if ($lastSlashPos !== false) {
                            $flags = substr($pattern, $lastSlashPos + 1);
                            if (strpos($flags, 'i') === false) {
                                $pattern = substr($pattern, 0, $lastSlashPos + 1) . 'i' . $flags;
                            }
                        }
                    }
                } else {
                    // New pattern without delimiters needs them added
                    $pattern = '/' . str_replace('/', '\/', $valueString) . '/';
                    if (!$caseSensitive) {
                        $pattern .= 'i';
                    }
                }
                
                // Use error suppression in case of invalid regex pattern
                $result = @preg_match($pattern, $fieldString);
                if ($result === false) {
                    // Invalid regex pattern
                    return false;
                }
                
                return $result === 1;

            default:
                return false;
        }
    }

    /**
     * Evaluate a scalar array condition
     *
     * @param array  $arrayValue     The array to evaluate
     * @param mixed  $conditionValue The condition value to compare against
     * @param string $operator       The operator to use
     * @param bool   $caseSensitive  Whether to do case-sensitive comparison
     * @return bool Whether the condition is met
     */
    protected function evaluateScalarArrayCondition(array $arrayValue, mixed $conditionValue, string $operator, bool $caseSensitive): bool
    {
        if (empty($arrayValue)) {
            return false;
        }

        // For contains operator, check if any element matches
        if ($operator === self::OPERATOR_CONTAINS) {
            foreach($arrayValue as $value) {
                if ($this->compareScalarValues($value, $conditionValue, self::OPERATOR_EQUALS, $caseSensitive)) {
                    return true;
                }
            }
            return false;
        }

        // For equals, greater_than, less_than, regex, check if any element satisfies
        foreach($arrayValue as $value) {
            if ($this->compareScalarValues($value, $conditionValue, $operator, $caseSensitive)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Compare scalar values
     *
     * @param mixed  $fieldValue     The field value to compare
     * @param mixed  $conditionValue The condition value to compare against
     * @param string $operator       The operator to use
     * @param bool   $caseSensitive  Whether to do case-sensitive comparison
     * @return bool Whether the condition is met
     */
    protected function compareScalarValues(mixed $fieldValue, mixed $conditionValue, string $operator, bool $caseSensitive): bool
    {
        // Determine data type for comparison
        if (is_bool($fieldValue) || is_bool($conditionValue)) {
            return $this->evaluateBooleanCondition($fieldValue, $conditionValue, $operator);
        }

        if (is_numeric($fieldValue) && is_numeric($conditionValue)) {
            return $this->evaluateNumberCondition($fieldValue, $conditionValue, $operator);
        }

        // Default to string comparison
        return $this->evaluateStringCondition($fieldValue, $conditionValue, $operator, $caseSensitive);
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

        if (count($data) === 1) {
            $key   = array_key_first($data);
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
        foreach($array as $value) {
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
        switch($dataType) {
            case 'boolean':
                return [self::OPERATOR_EQUALS, self::OPERATOR_EXISTS, self::OPERATOR_IS_TRUE, self::OPERATOR_IS_FALSE];

            case 'number':
                return [self::OPERATOR_EQUALS, self::OPERATOR_GREATER_THAN, self::OPERATOR_LESS_THAN, self::OPERATOR_EXISTS];

            case 'date':
                return [self::OPERATOR_EQUALS, self::OPERATOR_GREATER_THAN, self::OPERATOR_LESS_THAN, self::OPERATOR_EXISTS];

            case 'string':
                // Added greater_than and less_than for string lexicographical comparison
                return [self::OPERATOR_CONTAINS, self::OPERATOR_EQUALS, self::OPERATOR_GREATER_THAN, self::OPERATOR_LESS_THAN, self::OPERATOR_REGEX, self::OPERATOR_EXISTS];

            case 'array':
                return [self::OPERATOR_CONTAINS, self::OPERATOR_EXISTS];

            case 'unknown':
            default:
                // Return all supported operators for unknown or default cases
                return [self::OPERATOR_CONTAINS, self::OPERATOR_EQUALS, self::OPERATOR_GREATER_THAN, self::OPERATOR_LESS_THAN, self::OPERATOR_REGEX, self::OPERATOR_EXISTS];
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

        $type   = $leafNode['type'] ?? 'unknown';
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
        $childKey   = key($fragmentSelector['children']);

        // If this child has no children of its own and has a type, it's a leaf node
        if (isset($firstChild['type']) && (!isset($firstChild['children']) || empty($firstChild['children']))) {
            return $firstChild;
        }

        // Otherwise, recursively search for a leaf node
        return $this->findFirstLeafNode($firstChild);
    }
}
