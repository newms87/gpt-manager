<?php

namespace App\Services;

use App\Traits\HasDebugLogging;

/**
 * Unified value formatting service
 *
 * Provides consistent formatting for various data types across the application.
 * Uses ArrayAggregationService for aggressive numeric value parsing.
 *
 * Example usage:
 *
 *     $formatter = app(ValueFormattingService::class);
 *
 *     // Format currency
 *     $formatted = $formatter->formatCurrency('$1,234.56', 'USD', 2);
 *     // Returns: "$1,234.56"
 *
 *     // Format percentage
 *     $formatted = $formatter->formatPercentage(0.45, 2);
 *     // Returns: "45.00%"
 *
 *     // Using the main format method
 *     $formatted = $formatter->format('1234.567', 'decimal', ['decimals' => 2]);
 *     // Returns: "1,234.57"
 */
class ValueFormattingService
{
    use HasDebugLogging;

    /**
     * Format a value based on the specified format type
     *
     * @param  mixed  $value  The value to format
     * @param  string  $formatType  The format type: 'text', 'integer', 'decimal', 'currency', 'percentage', 'date'
     * @param  array  $options  Formatting options (decimals, currencyCode, dateFormat)
     * @return string The formatted value
     */
    public function format(
        mixed $value,
        string $formatType = 'text',
        array $options = []
    ): string {
        static::log('Formatting value', [
            'format_type'  => $formatType,
            'value_type'   => gettype($value),
            'value_length' => is_string($value) ? strlen($value) : null,
        ]);

        return match ($formatType) {
            'text'     => $this->formatText($value),
            'integer'  => $this->formatInteger($value),
            'decimal'  => $this->formatDecimal($value, $options['decimals'] ?? 2),
            'currency' => $this->formatCurrency(
                $value,
                $options['currencyCode'] ?? 'USD',
                $options['decimals']     ?? 2
            ),
            'percentage' => $this->formatPercentage($value, $options['decimals'] ?? 2),
            'date'       => $this->formatDate($value),
            default      => $this->formatText($value),
        };
    }

    /**
     * Format value as text (return as-is, converting to string if needed)
     *
     * @param  mixed  $value  The value to format
     * @return string The value as a string
     */
    public function formatText(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (string)$value;
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return '';
    }

    /**
     * Format value as integer with thousands separator
     *
     * @param  mixed  $value  The value to format
     * @return string The formatted integer (e.g., "1,234")
     */
    public function formatInteger(mixed $value): string
    {
        $numeric = app(ArrayAggregationService::class)->parseNumericValue($value);
        if ($numeric === null) {
            return $this->formatText($value);
        }

        return number_format(round($numeric), 0, '.', ',');
    }

    /**
     * Format value as decimal with specified decimal places
     *
     * @param  mixed  $value  The value to format
     * @param  int  $decimals  Number of decimal places (default: 2)
     * @return string The formatted decimal (e.g., "1,234.56")
     */
    public function formatDecimal(mixed $value, int $decimals = 2): string
    {
        $numeric = app(ArrayAggregationService::class)->parseNumericValue($value);
        if ($numeric === null) {
            return $this->formatText($value);
        }

        return number_format($numeric, $decimals, '.', ',');
    }

    /**
     * Format value as currency
     *
     * @param  mixed  $value  The value to format
     * @param  string  $currencyCode  Currency code (e.g., 'USD', 'EUR', 'GBP')
     * @param  int  $decimals  Number of decimal places (default: 2)
     * @return string The formatted currency (e.g., "$1,234.56", "1,234.56 €")
     */
    public function formatCurrency(mixed $value, string $currencyCode = 'USD', int $decimals = 2): string
    {
        $numeric = app(ArrayAggregationService::class)->parseNumericValue($value);
        if ($numeric === null) {
            return $this->formatText($value);
        }

        // Format the number with thousands separator
        $formatted = number_format($numeric, $decimals, '.', ',');

        // Add currency symbol based on currency code
        $symbol = match (strtoupper($currencyCode)) {
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY', 'CNY' => '¥',
            default => $currencyCode . ' ',
        };

        // For most currencies, symbol goes before; for some it goes after
        $symbolAfter = in_array(strtoupper($currencyCode), ['EUR']);

        return $symbolAfter ? $formatted . ' ' . $symbol : $symbol . $formatted;
    }

    /**
     * Format value as percentage
     *
     * Smart percentage handling:
     * - If value is less than 1, assumes it's a decimal (0.45 → 45%)
     * - If value is >= 1, assumes it's already a percentage (45 → 45%)
     *
     * @param  mixed  $value  The value to format
     * @param  int  $decimals  Number of decimal places (default: 2)
     * @return string The formatted percentage (e.g., "45.00%")
     */
    public function formatPercentage(mixed $value, int $decimals = 2): string
    {
        $numeric = app(ArrayAggregationService::class)->parseNumericValue($value);
        if ($numeric === null) {
            return $this->formatText($value);
        }

        // If value is less than 1, assume it's a decimal (0.45 -> 45%)
        // If value is >= 1, assume it's already a percentage (45 -> 45%)
        if ($numeric < 1 && $numeric > 0) {
            $numeric *= 100;
        }

        return number_format($numeric, $decimals, '.', ',') . '%';
    }

    /**
     * Format ISO date to readable format
     *
     * Converts ISO date strings (YYYY-MM-DD) to human-readable format.
     * Format: "May 25th, 2025"
     *
     * @param  mixed  $value  The value to format (expects ISO date string)
     * @return string The formatted date or original value if not a valid date
     */
    public function formatDate(mixed $value): string
    {
        if (!is_string($value)) {
            return $this->formatText($value);
        }

        // Check if it's a valid ISO date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            return $value;
        }

        try {
            $date = new \DateTime($value);
            // Format as: May 25th, 2025
            $day    = (int)$date->format('j');
            $suffix = match (true) {
                $day % 10 === 1 && $day !== 11 => 'st',
                $day % 10 === 2 && $day !== 12 => 'nd',
                $day % 10 === 3 && $day !== 13 => 'rd',
                default                        => 'th',
            };

            return $date->format('F') . ' ' . $day . $suffix . ', ' . $date->format('Y');
        } catch (\Exception $e) {
            static::log('Date formatting failed', [
                'value' => $value,
                'error' => $e->getMessage(),
            ]);

            return $value;
        }
    }
}
