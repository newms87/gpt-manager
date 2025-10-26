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
     * @param  mixed  $fieldValue  The field value to evaluate the condition against
     * @param  array  $condition  The condition to evaluate
     * @return bool Whether the data matches the condition
     */
    public function evaluateCondition(mixed $fieldValue, array $condition): bool
    {
        $operator      = $condition['operator']       ?? self::OPERATOR_CONTAINS;
        $value         = $condition['value']          ?? null;
        $caseSensitive = $condition['case_sensitive'] ?? false;

        $this->debugLog('evaluateCondition with value: ' . json_encode($fieldValue));
        $this->debugLog('and condition: ' . json_encode($condition));

        // Handle possible JSON strings
        $fieldValue = $this->parseJsonString($fieldValue);

        // Handle special cases first
        if ($operator === self::OPERATOR_EXISTS) {
            return $this->evaluateExists($fieldValue);
        }

        if ($fieldValue === null) {
            return false;
        }

        // Handle boolean operators
        if ($this->isBooleanOperator($operator)) {
            return $this->evaluateBooleanOperator($fieldValue, $operator);
        }

        // Handle fragment selectors for JSON paths
        if (isset($condition['fragment_selector']) && is_array($fieldValue)) {
            $result = $this->evaluateWithFragmentSelector($fieldValue, $condition, $operator, $value, $caseSensitive);
            if ($result !== null) {
                return $result;
            }
        }

        // Handle array values
        if (is_array($fieldValue)) {
            return $this->evaluateArrayValue($fieldValue, $value, $operator, $caseSensitive);
        }

        // Handle Collection types
        if ($fieldValue instanceof Collection) {
            return $this->evaluateCollectionCondition($fieldValue, $operator, $value);
        }

        // For scalar values, do direct comparison
        return $this->compareValues($fieldValue, $value, $operator, $caseSensitive);
    }

    /**
     * Parse a string that might be JSON into an array
     *
     * @param  mixed  $value  The value to parse
     * @return mixed Parsed value or original value if not JSON
     */
    protected function parseJsonString(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        // Check if this looks like a JSON string
        if ((str_starts_with($value, '[') && str_ends_with($value, ']')) ||
            (str_starts_with($value, '{') && str_ends_with($value, '}'))) {
            try {
                $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    $this->debugLog('Decoded JSON string to array: ' . json_encode($decoded));

                    return $decoded;
                }
            } catch (\Exception $e) {
                $this->debugLog('Failed to decode JSON string: ' . $e->getMessage());
            }
        }

        return $value;
    }

    /**
     * Evaluate exists operator
     *
     * @param  mixed  $value  The value to check existence for
     * @return bool Whether the value exists
     */
    protected function evaluateExists(mixed $value): bool
    {
        return $value !== null && (!is_array($value) || !empty($value));
    }

    /**
     * Evaluate boolean operator (is_true, is_false)
     *
     * @param  mixed  $value  The value to evaluate
     * @param  string  $operator  The boolean operator
     * @return bool The evaluation result
     */
    protected function evaluateBooleanOperator(mixed $value, string $operator): bool
    {
        $targetValue = ($operator === self::OPERATOR_IS_TRUE);

        // For scalar, direct comparison
        if (is_scalar($value)) {
            return $value === $targetValue;
        }

        // For arrays, check if any element matches
        if (is_array($value)) {
            foreach ($value as $item) {
                if ($item === $targetValue) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Evaluate a condition using fragment selector
     *
     * @param  array  $fieldValue  The field value (array)
     * @param  array  $condition  The condition with fragment selector
     * @param  string  $operator  The operator
     * @param  mixed  $conditionValue  The value to compare against
     * @param  bool  $caseSensitive  Case sensitivity flag
     * @return bool|null Result or null if evaluation couldn't be done with fragment
     */
    protected function evaluateWithFragmentSelector(
        array $fieldValue,
        array $condition,
        string $operator,
        mixed $conditionValue,
        bool $caseSensitive
    ): ?bool {
        // Extract using path
        $path = $this->getPathFromFragmentSelector($condition['fragment_selector']);
        $this->debugLog('Path from fragment selector: ' . implode('.', $path));

        $extractedValue = $this->getValueByPath($fieldValue, $path);
        $this->debugLog('Extracted value by path: ' . json_encode($extractedValue));

        // If we successfully extracted a value, compare it
        if ($extractedValue !== null) {
            return $this->compareValues($extractedValue, $conditionValue, $operator, $caseSensitive);
        }

        // Check for special array format from getJsonFragmentValue
        if (count($fieldValue) === 1) {
            $key = array_key_first($fieldValue);
            $val = $fieldValue[$key];

            // If the key is the same as the last path segment, this might be our target
            if (!empty($path) && $key === end($path)) {
                $this->debugLog('Found value using special array format: ' . json_encode($val));

                return $this->compareValues($val, $conditionValue, $operator, $caseSensitive);
            }
        }

        return null;
    }

    /**
     * Evaluate an array value against a condition
     *
     * @param  array  $fieldValue  The array to evaluate
     * @param  mixed  $conditionValue  The value to compare against
     * @param  string  $operator  The operator
     * @param  bool  $caseSensitive  Case sensitivity flag
     * @return bool The evaluation result
     */
    protected function evaluateArrayValue(
        array $fieldValue,
        mixed $conditionValue,
        string $operator,
        bool $caseSensitive
    ): bool {
        // For scalar arrays, check if any element matches
        if ($this->isScalarArray($fieldValue)) {
            foreach ($fieldValue as $item) {
                if ($this->compareValues($item, $conditionValue, $operator, $caseSensitive)) {
                    return true;
                }
            }

            return false;
        }

        // For complex arrays with text operators, convert to string
        if (in_array($operator, [self::OPERATOR_CONTAINS, self::OPERATOR_EQUALS, self::OPERATOR_REGEX])) {
            $stringValue = json_encode($fieldValue);

            return $this->compareValues($stringValue, $conditionValue, $operator, $caseSensitive);
        }

        return false;
    }

    /**
     * Compare two values based on the operator
     *
     * @param  mixed  $fieldValue  The field value
     * @param  mixed  $conditionValue  The condition value
     * @param  string  $operator  The operator to use
     * @param  bool  $caseSensitive  Whether to use case-sensitive comparison
     * @return bool Whether the values match according to the operator
     */
    protected function compareValues(mixed $fieldValue, mixed $conditionValue, string $operator, bool $caseSensitive): bool
    {
        // Handle null cases
        if ($fieldValue === null || $conditionValue === null) {
            return $operator === self::OPERATOR_EQUALS && $fieldValue === $conditionValue;
        }

        // Choose comparison strategy based on value types
        if (is_bool($fieldValue) || is_bool($conditionValue)) {
            return $this->compareBooleanValues($fieldValue, $conditionValue, $operator);
        } elseif (is_numeric($fieldValue) && is_numeric($conditionValue)) {
            return $this->compareNumericValues($fieldValue, $conditionValue, $operator);
        } else {
            return $this->compareStringValues($fieldValue, $conditionValue, $operator, $caseSensitive);
        }
    }

    /**
     * Compare boolean values
     *
     * @param  mixed  $fieldValue  Field value
     * @param  mixed  $conditionValue  Condition value
     * @param  string  $operator  Operator to use
     * @return bool Comparison result
     */
    protected function compareBooleanValues(mixed $fieldValue, mixed $conditionValue, string $operator): bool
    {
        $fieldBool = $this->extractBooleanValue($fieldValue)     ?? false;
        $valueBool = $this->extractBooleanValue($conditionValue) ?? false;

        return match ($operator) {
            self::OPERATOR_EQUALS   => $fieldBool === $valueBool,
            self::OPERATOR_CONTAINS => $fieldBool === $valueBool,
            default                 => false
        };
    }

    /**
     * Compare numeric values
     *
     * @param  mixed  $fieldValue  Field value
     * @param  mixed  $conditionValue  Condition value
     * @param  string  $operator  Operator to use
     * @return bool Comparison result
     */
    protected function compareNumericValues(mixed $fieldValue, mixed $conditionValue, string $operator): bool
    {
        $fieldNum = (float)$fieldValue;
        $valueNum = (float)$conditionValue;

        return match ($operator) {
            self::OPERATOR_EQUALS       => $fieldNum == $valueNum,
            self::OPERATOR_GREATER_THAN => $fieldNum > $valueNum,
            self::OPERATOR_LESS_THAN    => $fieldNum < $valueNum,
            self::OPERATOR_CONTAINS     => (string)$fieldNum === (string)$valueNum,
            default                     => false
        };
    }

    /**
     * Compare string values
     *
     * @param  mixed  $fieldValue  Field value
     * @param  mixed  $conditionValue  Condition value
     * @param  string  $operator  Operator to use
     * @param  bool  $caseSensitive  Case sensitivity flag
     * @return bool Comparison result
     */
    protected function compareStringValues(mixed $fieldValue, mixed $conditionValue, string $operator, bool $caseSensitive): bool
    {
        $fieldStr = (string)$fieldValue;
        $valueStr = (string)$conditionValue;

        if (!$caseSensitive && $operator !== self::OPERATOR_REGEX) {
            $fieldStr = strtolower($fieldStr);
            $valueStr = strtolower($valueStr);
        }

        return match ($operator) {
            self::OPERATOR_EQUALS       => $fieldStr === $valueStr,
            self::OPERATOR_CONTAINS     => str_contains($fieldStr, $valueStr),
            self::OPERATOR_GREATER_THAN => $fieldStr > $valueStr,
            self::OPERATOR_LESS_THAN    => $fieldStr < $valueStr,
            self::OPERATOR_REGEX        => $this->regexMatch($fieldStr, $valueStr, $caseSensitive),
            default                     => false
        };
    }

    /**
     * Check if a regex pattern matches a string
     *
     * @param  string  $subject  The string to check
     * @param  string  $pattern  The regex pattern
     * @param  bool  $caseSensitive  Whether to do case-sensitive comparison
     * @return bool Whether the pattern matches
     */
    protected function regexMatch(string $subject, string $pattern, bool $caseSensitive): bool
    {
        if (empty($pattern)) {
            return false;
        }

        try {
            $finalPattern = $this->prepareRegexPattern($pattern, $caseSensitive);

            return preg_match($finalPattern, $subject) === 1;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Prepare a regex pattern to be used with preg_match
     *
     * @param  string  $pattern  Original pattern
     * @param  bool  $caseSensitive  Whether to do case-sensitive matching
     * @return string Prepared pattern
     */
    protected function prepareRegexPattern(string $pattern, bool $caseSensitive): string
    {
        // Determine if the pattern already includes delimiters
        $hasDelimiters = strlen($pattern) > 1        &&
                        $pattern[0]          === '/' &&
                        substr($pattern, -1) === '/';

        // For patterns with existing delimiters, preserve them
        if ($hasDelimiters) {
            $finalPattern = $pattern;

            // If case-insensitive and the pattern doesn't already have 'i' flag, add it
            if (!$caseSensitive && strpos($finalPattern, 'i') === false) {
                $lastSlashPos = strrpos($finalPattern, '/');
                if ($lastSlashPos !== false) {
                    $finalPattern = substr($finalPattern, 0, $lastSlashPos + 1) . 'i' . substr($finalPattern, $lastSlashPos + 1);
                }
            }
        } else {
            // New pattern without delimiters needs them added
            $finalPattern = '/' . str_replace('/', '\/', $pattern) . '/';
            if (!$caseSensitive) {
                $finalPattern .= 'i';
            }
        }

        return $finalPattern;
    }

    /**
     * Extract a boolean value from various formats
     *
     * @param  mixed  $value  The value to extract from
     * @return bool|null The extracted boolean or null if extraction failed
     */
    protected function extractBooleanValue(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            if (strtolower($value) === 'true') {
                return true;
            } elseif (strtolower($value) === 'false') {
                return false;
            }
        }

        if (is_numeric($value)) {
            if ($value === 1 || $value === '1') {
                return true;
            } elseif ($value === 0 || $value === '0') {
                return false;
            }
        }

        if (is_array($value) && count($value) === 1) {
            // Try to extract a single boolean from an array
            $firstValue = reset($value);
            if (is_bool($firstValue)) {
                return $firstValue;
            }
        }

        return null;
    }

    /**
     * Check if an operator is a boolean operator
     *
     * @param  string  $operator  The operator to check
     * @return bool Whether it's a boolean operator
     */
    protected function isBooleanOperator(string $operator): bool
    {
        return in_array($operator, [self::OPERATOR_IS_TRUE, self::OPERATOR_IS_FALSE]);
    }

    /**
     * Evaluate a condition on a collection
     *
     * @param  Collection  $collection  The collection to evaluate
     * @param  string  $operator  The operator to use
     * @param  mixed  $value  The value to compare against
     * @return bool Whether the collection matches the condition
     */
    protected function evaluateCollectionCondition(Collection $collection, string $operator, mixed $value): bool
    {
        // For collections, check if any element matches the condition
        foreach ($collection as $item) {
            if ($this->compareValues($item, $value, $operator, false)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if an array contains only scalar values
     *
     * @param  array  $array  The array to check
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
     * Get path array from a fragment selector
     *
     * @param  array  $fragmentSelector  Fragment selector definition
     * @return array Path array
     */
    protected function getPathFromFragmentSelector(array $fragmentSelector): array
    {
        $path    = [];
        $current = $fragmentSelector;

        while (!empty($current['children'])) {
            $key     = array_key_first($current['children']);
            $path[]  = $key;
            $current = $current['children'][$key];
        }

        return $path;
    }

    /**
     * Get a value from an array by path
     *
     * @param  array  $data  Data to extract from
     * @param  array  $path  Path to the desired value
     * @return mixed The value or null if not found
     */
    protected function getValueByPath(array $data, array $path): mixed
    {
        $current = $data;

        foreach ($path as $key) {
            if (!is_array($current) || !isset($current[$key])) {
                return null;
            }
            $current = $current[$key];
        }

        return $current;
    }

    /**
     * Log debug messages if app.debug is enabled
     *
     * @param  string  $message  The message to log
     */
    protected function debugLog(string $message): void
    {
        if (config('app.debug')) {
            logger()->debug("[FilterService] $message");
        }
    }

    /**
     * Validate a condition structure
     *
     * @param  array  $condition  Condition to validate
     *
     * @throws ValidationError if the condition is invalid
     */
    public function validateCondition(array $condition): void
    {
        if (!isset($condition['field'])) {
            throw new ValidationError("Condition must have a 'field' property");
        }

        if (isset($condition['operator'])) {
            $operator       = $condition['operator'];
            $validOperators = [
                self::OPERATOR_EQUALS,
                self::OPERATOR_CONTAINS,
                self::OPERATOR_GREATER_THAN,
                self::OPERATOR_LESS_THAN,
                self::OPERATOR_REGEX,
                self::OPERATOR_EXISTS,
                self::OPERATOR_IS_TRUE,
                self::OPERATOR_IS_FALSE,
            ];

            if (!in_array($operator, $validOperators)) {
                throw new ValidationError("Invalid operator: '$operator'");
            }

            // Value is required for all operators except exists, is_true, and is_false
            if (!in_array($operator, [self::OPERATOR_EXISTS, self::OPERATOR_IS_TRUE, self::OPERATOR_IS_FALSE])
                && !array_key_exists('value', $condition)) {
                throw new ValidationError("Condition with operator '$operator' must have a 'value' property");
            }
        }
    }

    /**
     * Get available operators for a specific data type
     *
     * @param  string  $dataType  The data type
     * @return array Array of valid operators
     */
    public function getOperatorsForDataType(string $dataType): array
    {
        switch ($dataType) {
            case 'boolean':
                return [self::OPERATOR_EQUALS, self::OPERATOR_EXISTS, self::OPERATOR_IS_TRUE, self::OPERATOR_IS_FALSE];

            case 'number':
                return [self::OPERATOR_EQUALS, self::OPERATOR_GREATER_THAN, self::OPERATOR_LESS_THAN, self::OPERATOR_EXISTS];

            case 'date':
                return [self::OPERATOR_EQUALS, self::OPERATOR_GREATER_THAN, self::OPERATOR_LESS_THAN, self::OPERATOR_EXISTS];

            case 'string':
                return [self::OPERATOR_CONTAINS, self::OPERATOR_EQUALS, self::OPERATOR_GREATER_THAN, self::OPERATOR_LESS_THAN, self::OPERATOR_REGEX, self::OPERATOR_EXISTS];

            case 'array':
                return [self::OPERATOR_CONTAINS, self::OPERATOR_EXISTS];

            case 'unknown':
            default:
                return [self::OPERATOR_CONTAINS, self::OPERATOR_EQUALS, self::OPERATOR_GREATER_THAN, self::OPERATOR_LESS_THAN, self::OPERATOR_REGEX, self::OPERATOR_EXISTS];
        }
    }

    /**
     * Get the field value from the data
     *
     * @param  mixed  $data  The data to get the field value from
     * @param  string  $field  The field to get the value from
     * @param  array|null  $fragmentSelector  The fragment selector to apply
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
            // No need to wrap, we'll extract when evaluating the condition
            return $value;
        }

        return $value;
    }
}
