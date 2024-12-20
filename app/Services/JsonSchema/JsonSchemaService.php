<?php

namespace App\Services\JsonSchema;

use Exception;

class JsonSchemaService
{
    /**
     * Recursively filters a JSON schema by a sub-selection of properties
     */
    public static function filterSchemaBySubSelection(array $schema, array $subSelection = null): array
    {
        if (!$subSelection) {
            return $schema;
        }

        if (empty($subSelection['children'])) {
            return [];
        }

        $properties = $schema['properties'] ?? [];
        $filtered   = [];

        foreach($subSelection['children'] as $selectedKey => $selectedProperty) {
            if (empty($selectedProperty['type'])) {
                throw new Exception("Sub-selection must have a type: $selectedKey: " . json_encode($selectedProperty));
            }

            // Skip if the property is not in the schema
            // NOTE: We do not throw an error here because sub selections are not directly tied to schemas. They are loosely correlated, but schemas may change while selections remain the same.
            if (empty($properties[$selectedKey])) {
                continue;
            }

            if ($selectedProperty['type'] === 'object') {
                $result = static::filterSchemaBySubSelection($properties[$selectedKey], $selectedProperty);
            } elseif ($selectedProperty['type'] === 'array') {
                $result = static::filterSchemaBySubSelection($properties[$selectedKey]['items'], $selectedProperty);
            } else {
                $result = $properties[$selectedKey];
            }

            if ($result) {
                if ($selectedProperty['type'] === 'array') {
                    $filtered[$selectedKey] = [
                        ...$properties[$selectedKey],
                        'type'  => 'array',
                        'items' => $result,
                    ];
                } else {
                    $filtered[$selectedKey] = $result;
                }
            }
        }

        // Take advantage of copy on write to avoid modifying the original schema, and return the updated schema with filtered properties
        $schema['properties'] = $filtered;

        return $schema;
    }

    /**
     * Format and clean the schema to match the requirements of a strict JSON schema
     *
     * @param string $name   The name for this version of the schema
     * @param array  $schema The schema to format. The schema should be properly formatted JSON schema (starting with
     *                       properties of an object as they will be nested inside the main schema)
     * @param int    $depth  The depth of the schema (used for recursion)
     * @return array
     * @throws Exception
     */
    public static function formatAndCleanSchema(string $name, array $schema, int $depth = 0): array
    {
        if (!$schema) {
            return [];
        }

        $formattedSchema = [];

        // Resolve the properties of the schema. It's possible the schema is an array of properties already, so just parse from the array instead.
        $properties = $schema['properties'] ?? $schema;

        // Ensures all properties (and sub properties) are both required and have no additional properties. It does this recursively.
        foreach($properties as $key => $value) {
            $childName     = $name . '.' . $key;
            $formattedItem = static::formatAndCleanSchemaItem($childName, $value, $depth);
            if ($formattedItem) {
                $formattedSchema[$key] = $formattedItem;
            }
        }

        if ($depth > 0) {
            return $formattedSchema;
        }

        $formattedSchema = [
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => array_keys($formattedSchema),
            'properties'           => $formattedSchema,
        ];

        if (array_key_exists('title', $schema)) {
            $formattedSchema['title'] = $schema['title'];
        }

        if (array_key_exists('description', $schema)) {
            $formattedSchema['description'] = $schema['description'];
        }

        return [
            'name'   => $name,
            'strict' => true,
            'schema' => $formattedSchema,
        ];
    }

    /**
     * Format and clean the schema property entry or array item to match the requirements of a strict JSON schema
     */
    public static function formatAndCleanSchemaItem($name, $value, $depth = 0): array|null
    {
        $type        = $value['type'] ?? null;
        $title       = $value['title'] ?? null;
        $description = $value['description'] ?? null;
        $enum        = $value['enum'] ?? null;
        $properties  = $value['properties'] ?? [];
        $items       = $value['items'] ?? [];

        $resolvedType = is_array($type) ? $type[0] : $type;

        $item = match ($resolvedType) {
            'object' => [
                'type'                 => $type,
                'properties'           => static::formatAndCleanSchema("$name.properties", $properties, $depth + 1),
                'additionalProperties' => false,
            ],
            'array' => [
                'type'  => $type,
                'items' => static::formatAndCleanSchemaItem("$name.items", $items, $depth + 1),
            ],
            'string', 'number', 'integer', 'boolean', 'null' => ['type' => $type],
            default => throw new Exception("Unknown type at path $name: " . $type),
        };

        // If the type is an object with no properties, it is an empty object and can be ignored
        if ($resolvedType === 'array' && !$item['items'] ||
            $resolvedType === 'object' && !$item['properties']) {
            return null;
        }

        if ($resolvedType === 'object') {
            $item['required'] = array_keys($item['properties']);
        }

        if ($title) {
            $item['title'] = $title;
        }

        if ($description) {
            $item['description'] = $description;
        }

        if ($enum) {
            $item['enum'] = $enum;
        }

        return $item;
    }
}
