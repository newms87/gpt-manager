<?php

namespace App\Services\Task\DataExtraction;

use App\Models\Schema\SchemaDefinition;

/**
 * Extracts object type information from a SchemaDefinition.
 *
 * Analyzes JSON schema structure to identify all object types (root and nested),
 * classifies their fields as simple or complex, and builds hierarchical metadata
 * for each object type.
 */
class ObjectTypeExtractor
{
    /**
     * Extract all object types from a SchemaDefinition.
     *
     * Returns an array of object type structures containing:
     * - name: Human-readable name (from title or property key)
     * - path: JSON path in schema (dot notation for nested objects)
     * - level: Hierarchy level (0 = root)
     * - parent_type: Parent object type name (null for root)
     * - is_array: Whether it's an array of objects
     * - simple_fields: Non-object, non-array-of-objects fields with their metadata
     */
    public function extractObjectTypes(SchemaDefinition $schemaDefinition): array
    {
        $schema = $schemaDefinition->schema;

        if (!isset($schema['type']) || $schema['type'] !== 'object') {
            return [];
        }

        $objectTypes = [];

        // Extract root object
        $rootName       = $schema['title'] ?? $schemaDefinition->name;
        $rootObjectType = [
            'name'          => $rootName,
            'path'          => '',
            'level'         => 0,
            'parent_type'   => null,
            'is_array'      => false,
            'simple_fields' => $this->extractSimpleFields($schema['properties'] ?? []),
        ];

        $objectTypes[] = $rootObjectType;

        // Extract nested objects
        $nestedTypes = $this->extractNestedObjects(
            $schema['properties'] ?? [],
            level: 1,
            parentType: $rootName,
            parentPath: ''
        );

        return array_merge($objectTypes, $nestedTypes);
    }

    /**
     * Extract simple (non-object, non-array-of-objects) fields from properties.
     */
    protected function extractSimpleFields(array $properties): array
    {
        $simpleFields = [];

        foreach ($properties as $key => $property) {
            if ($this->isSimpleField($property)) {
                $simpleFields[$key] = [
                    'title'       => $property['title']       ?? $this->convertKeyToTitle($key),
                    'description' => $property['description'] ?? null,
                ];
            }
        }

        return $simpleFields;
    }

    /**
     * Determine if a property is a simple field.
     *
     * Simple fields are:
     * - Scalar types (string, number, integer, boolean)
     * - Arrays of primitives (array with items.type NOT object)
     *
     * NOT simple:
     * - Objects (type = object)
     * - Arrays of objects (type = array, items.type = object)
     */
    protected function isSimpleField(array $property): bool
    {
        $type = $property['type'] ?? null;

        // Handle union types (e.g., ['string', 'null'])
        if (is_array($type)) {
            $type = $this->getPrimaryType($type);
        }

        // Scalar types are simple
        if (in_array($type, ['string', 'number', 'integer', 'boolean', 'null'])) {
            return true;
        }

        // Objects are NOT simple
        if ($type === 'object') {
            return false;
        }

        // Arrays: check if items are objects
        if ($type === 'array') {
            $itemsType = $property['items']['type'] ?? null;

            // Handle union types in items
            if (is_array($itemsType)) {
                $itemsType = $this->getPrimaryType($itemsType);
            }

            // Array of objects is NOT simple
            if ($itemsType === 'object') {
                return false;
            }

            // Array of primitives IS simple
            return true;
        }

        // Unknown types default to simple
        return true;
    }

    /**
     * Extract nested object types recursively.
     */
    protected function extractNestedObjects(
        array $properties,
        int $level,
        string $parentType,
        string $parentPath
    ): array {
        $objectTypes = [];

        foreach ($properties as $key => $property) {
            $type = $property['type'] ?? null;

            // Handle union types
            if (is_array($type)) {
                $type = $this->getPrimaryType($type);
            }

            // Direct object property
            if ($type === 'object') {
                $nestedPath    = $parentPath ? "$parentPath.$key" : $key;
                $objectTypes[] = $this->buildObjectType(
                    property: $property,
                    key: $key,
                    path: $nestedPath,
                    level: $level,
                    parentType: $parentType,
                    isArray: false
                );

                // Recursively extract from nested object's properties
                if (isset($property['properties'])) {
                    $objectTypes = array_merge(
                        $objectTypes,
                        $this->extractNestedObjects(
                            $property['properties'],
                            $level + 1,
                            $property['title'] ?? $this->convertKeyToTitle($key),
                            $nestedPath
                        )
                    );
                }
            }

            // Array of objects
            if ($type === 'array') {
                $itemsType = $property['items']['type'] ?? null;

                // Handle union types in items
                if (is_array($itemsType)) {
                    $itemsType = $this->getPrimaryType($itemsType);
                }

                if ($itemsType === 'object') {
                    $nestedPath    = $parentPath ? "$parentPath.$key" : $key;
                    $objectTypes[] = $this->buildObjectType(
                        property: $property['items'],
                        key: $key,
                        path: $nestedPath,
                        level: $level,
                        parentType: $parentType,
                        isArray: true
                    );

                    // Recursively extract from array item's properties
                    if (isset($property['items']['properties'])) {
                        $objectTypes = array_merge(
                            $objectTypes,
                            $this->extractNestedObjects(
                                $property['items']['properties'],
                                $level + 1,
                                $property['items']['title'] ?? $this->convertKeyToTitle($key),
                                $nestedPath
                            )
                        );
                    }
                }
            }
        }

        return $objectTypes;
    }

    /**
     * Build an object type structure.
     */
    protected function buildObjectType(
        array $property,
        string $key,
        string $path,
        int $level,
        string $parentType,
        bool $isArray
    ): array {
        return [
            'name'          => $property['title'] ?? $this->convertKeyToTitle($key),
            'path'          => $path,
            'level'         => $level,
            'parent_type'   => $parentType,
            'is_array'      => $isArray,
            'simple_fields' => $this->extractSimpleFields($property['properties'] ?? []),
        ];
    }

    /**
     * Convert property key to Title Case.
     */
    protected function convertKeyToTitle(string $key): string
    {
        // Convert snake_case or camelCase to Title Case
        $words = preg_split('/[_\s]+|(?=[A-Z])/', $key);
        $words = array_filter($words); // Remove empty strings
        $words = array_map('ucfirst', $words);

        return implode(' ', $words);
    }

    /**
     * Get primary type from union type array.
     *
     * Filters out 'null' and returns the first non-null type.
     */
    protected function getPrimaryType(array $types): ?string
    {
        $filtered = array_filter($types, fn($t) => $t !== 'null');

        return reset($filtered) ?: null;
    }
}
