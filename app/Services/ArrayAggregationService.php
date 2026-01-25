<?php

namespace App\Services;

use Newms87\Danx\Traits\HasDebugLogging;

/**
 * Generic array aggregation service for calculating max, min, average, and sum
 *
 * This service provides reusable aggregation operations on arrays with:
 * - Aggressive value parsing (handles currency, percentages, scientific notation)
 * - Date support for MAX/MIN operations
 * - Automatic filtering of invalid values
 * - Comprehensive logging for debugging
 *
 * Example usage:
 *
 *     $service = app(ArrayAggregationService::class);
 *     $max = $service->max(['100', '$250.50', '75']); // Returns '250.5'
 *     $sum = $service->sum(['50%', '25%', '10%']); // Returns '85'
 *     $avg = $service->avg([100, 200, 150]); // Returns '150'
 */
class ArrayAggregationService
{
    use HasDebugLogging;

    /**
     * Calculate maximum value from array
     *
     * Supports both numeric values and ISO date strings.
     * Returns the largest numeric value or the latest date.
     *
     * @param  array  $values  Array of values to aggregate
     * @return string The maximum value as a string
     */
    public function max(array $values): string
    {
        return $this->calculateAggregate($values, 'max', supportsDates: true);
    }

    /**
     * Calculate minimum value from array
     *
     * Supports both numeric values and ISO date strings.
     * Returns the smallest numeric value or the earliest date.
     *
     * @param  array  $values  Array of values to aggregate
     * @return string The minimum value as a string
     */
    public function min(array $values): string
    {
        return $this->calculateAggregate($values, 'min', supportsDates: true);
    }

    /**
     * Calculate average value from array
     *
     * Only supports numeric values (dates not supported for averaging).
     * Non-numeric values are filtered out automatically.
     *
     * @param  array  $values  Array of values to aggregate
     * @return string The average value as a string
     */
    public function avg(array $values): string
    {
        return $this->calculateAggregate($values, 'avg', supportsDates: false);
    }

    /**
     * Calculate sum of values from array
     *
     * Only supports numeric values (dates not supported for summing).
     * Non-numeric values are filtered out automatically.
     *
     * @param  array  $values  Array of values to aggregate
     * @return string The sum as a string
     */
    public function sum(array $values): string
    {
        return $this->calculateAggregate($values, 'sum', supportsDates: false);
    }

    /**
     * Calculate aggregate value from array
     *
     * @param  array  $values  Array of values to aggregate
     * @param  string  $operation  The operation type: 'max', 'min', 'avg', 'sum'
     * @param  bool  $supportsDates  Whether to try parsing dates first (only for MAX/MIN)
     * @return string The calculated aggregate value
     */
    protected function calculateAggregate(
        array $values,
        string $operation,
        bool $supportsDates = false
    ): string {
        static::logDebug("Calculating {$operation}", ['value_count' => count($values)]);

        // Try dates first (only for MAX/MIN)
        if ($supportsDates) {
            $dateValues = $this->parseDateValues($values);
            if (!empty($dateValues)) {
                $result = $operation === 'max' ? max($dateValues) : min($dateValues);
                static::logDebug("{$operation} calculated from dates", ['result' => $result]);

                return $result;
            }
        }

        // Fall back to numeric values
        $numericValues = $this->extractNumericValues($values);
        if (empty($numericValues)) {
            static::logDebug("{$operation}: No valid numeric values found, returning 0");

            return '0';
        }

        $result = match ($operation) {
            'max' => (string)max($numericValues),
            'min' => (string)min($numericValues),
            'avg' => (string)(array_sum($numericValues) / count($numericValues)),
            'sum' => (string)array_sum($numericValues),
        };

        static::logDebug("{$operation} calculated", [
            'result'      => $result,
            'valid_count' => count($numericValues),
        ]);

        return $result;
    }

    /**
     * Extract numeric values from array (aggressive parsing)
     *
     * Automatically filters out non-numeric values and parses:
     * - Plain numbers: "100", "250.5"
     * - Currency: "$1,234.56", "€100", "£50"
     * - Percentages: "50%", "25.5%"
     * - Scientific notation: "1.5e3", "2E2"
     * - Numbers with spaces/commas: "1 234.56", "1,234.56"
     *
     * @param  array  $values  Array of values to extract numerics from
     * @return array Array of float values
     */
    protected function extractNumericValues(array $values): array
    {
        $numericValues = [];
        $skippedCount  = 0;

        foreach ($values as $value) {
            $parsed = $this->parseNumericValue($value);
            if ($parsed !== null) {
                $numericValues[] = $parsed;
            } else {
                $skippedCount++;
                static::logDebug('Skipped non-numeric value', [
                    'value' => substr((string)$value, 0, 100),
                    'type'  => gettype($value),
                ]);
            }
        }

        static::logDebug('Extracted numeric values', [
            'total_values'  => count($values),
            'numeric_count' => count($numericValues),
            'skipped_count' => $skippedCount,
        ]);

        return $numericValues;
    }

    /**
     * Parse a single value to numeric (aggressive extraction)
     *
     * Handles:
     * - Native numeric types (int, float)
     * - Currency symbols ($, €, £, ¥)
     * - Thousands separators (commas, spaces)
     * - Percentages (strips %)
     * - Negative numbers
     * - Scientific notation (1.5e3)
     *
     * @param  mixed  $value  Value to parse
     * @return float|null The parsed numeric value or null if not parseable
     */
    public function parseNumericValue(mixed $value): ?float
    {
        // Already numeric
        if (is_int($value) || is_float($value)) {
            return (float)$value;
        }

        // Not a string - can't parse
        if (!is_string($value)) {
            return null;
        }

        // Remove whitespace
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        // Strip currency symbols, commas, and spaces
        // Remove: $, €, £, ¥, commas, spaces
        $cleaned = preg_replace('/[\$€£¥,\s]/', '', $value);

        // Handle percentages: "50%" -> 50
        if (str_ends_with($cleaned, '%')) {
            $cleaned = rtrim($cleaned, '%');
        }

        // Try to parse as float
        // Allow: digits, decimal point, negative sign, scientific notation
        if (preg_match('/^-?\d*\.?\d+([eE][+-]?\d+)?$/', $cleaned)) {
            return (float)$cleaned;
        }

        return null;
    }

    /**
     * Parse date values from array
     *
     * Extracts valid ISO date strings (YYYY-MM-DD format) from the array.
     * Only returns values if ALL values in the array are valid dates.
     * This ensures we don't mix date and numeric comparisons.
     *
     * @param  array  $values  Array of values to parse
     * @return array Array of ISO date strings or empty array if not all are dates
     */
    protected function parseDateValues(array $values): array
    {
        $dateValues = [];

        foreach ($values as $value) {
            $parsed = $this->parseDate($value);
            if ($parsed !== null) {
                $dateValues[] = $parsed;
            }
        }

        return $dateValues;
    }

    /**
     * Parse a single value to date string
     *
     * Currently supports ISO format (YYYY-MM-DD) at the beginning of the string.
     * Can be extended to support other date formats as needed.
     *
     * @param  mixed  $value  Value to parse
     * @return string|null The parsed ISO date string or null if not a date
     */
    protected function parseDate(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        // Try to parse as date
        // Check for ISO date format or common date patterns
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            return $value;
        }

        return null;
    }
}
