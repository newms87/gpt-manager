<?php

namespace App\Traits;

use Carbon\Carbon;
use Exception;

/**
 * Provides helper methods for working with JSON schema field definitions.
 *
 * Used by services that need to:
 * - Look up field definitions in nested JSON schemas
 * - Determine field types (date, boolean, number, etc.)
 * - Normalize date values to ISO format
 */
trait SchemaFieldHelper
{
    /**
     * Recursively find a field definition in a potentially nested schema.
     *
     * Searches through nested properties to find the field definition.
     * Handles schemas like: {properties: {demand: {properties: {accident_date: {...}}}}}
     */
    protected function findFieldInSchema(string $fieldName, array $schema): ?array
    {
        $properties = $schema['properties'] ?? [];

        // Check if field exists at this level
        if (isset($properties[$fieldName]) && is_array($properties[$fieldName])) {
            return $properties[$fieldName];
        }

        // Recursively search in nested objects
        foreach ($properties as $propDef) {
            if (!is_array($propDef)) {
                continue;
            }

            // Check nested properties (for object types)
            if (isset($propDef['properties'])) {
                $found = $this->findFieldInSchema($fieldName, $propDef);
                if ($found) {
                    return $found;
                }
            }

            // Check items properties (for array types)
            if (isset($propDef['items']['properties'])) {
                $found = $this->findFieldInSchema($fieldName, $propDef['items']);
                if ($found) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * Normalize a date value to ISO format (YYYY-MM-DD).
     *
     * Uses Carbon to parse various date formats and outputs ISO format.
     */
    protected function normalizeDateValue(string $value): string
    {
        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (Exception) {
            // If parsing fails, return the original value
            return $value;
        }
    }

    /**
     * Determine the type of a field from the schema definition.
     *
     * Resolution priority:
     * 1. Schema format (date, date-time) - most specific
     * 2. Schema type (boolean, number, integer, string)
     * 3. Default to string
     *
     * @return string One of: 'date', 'date-time', 'boolean', 'number', 'integer', 'string'
     */
    protected function getSchemaFieldType(string $fieldName, ?array $schema): string
    {
        if (!$schema) {
            return 'string';
        }

        $fieldDef = $this->findFieldInSchema($fieldName, $schema);
        if (!$fieldDef) {
            return 'string';
        }

        // Check format first (more specific than type)
        $format = $fieldDef['format'] ?? null;
        if ($format === 'date') {
            return 'date';
        }
        if ($format === 'date-time') {
            return 'date-time';
        }

        // Check type
        $type = $fieldDef['type'] ?? null;

        return match ($type) {
            'boolean' => 'boolean',
            'number'  => 'number',
            'integer' => 'integer',
            default   => 'string',
        };
    }

    /**
     * Check if a field is a date field based on schema definition.
     */
    protected function isDateField(string $fieldName, ?array $schema): bool
    {
        $fieldType = $this->getSchemaFieldType($fieldName, $schema);

        return in_array($fieldType, ['date', 'date-time'], true);
    }
}
