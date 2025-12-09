<?php

namespace App\Services\Task\DataExtraction;

use App\Models\Schema\SchemaDefinition;

class SchemaHierarchyParser
{
    /**
     * Parse schema into hierarchical levels.
     */
    public function parseSchemaHierarchy(SchemaDefinition $schemaDefinition): array
    {
        $schema     = $schemaDefinition->schema;
        $properties = $schema['properties'] ?? [];

        $hierarchy = [
            'schema_name'        => $schemaDefinition->name,
            'schema_description' => $schema['description'] ?? '',
            'levels'             => [],
        ];

        // Level 0: Root object properties
        $rootLevel = [
            'level'      => 0,
            'properties' => [],
        ];

        foreach ($properties as $propName => $propDef) {
            $propertyInfo              = $this->parseProperty($propName, $propDef, 0);
            $rootLevel['properties'][] = $propertyInfo;

            // If this property contains nested objects, create additional levels
            if (!empty($propertyInfo['nested_levels'])) {
                foreach ($propertyInfo['nested_levels'] as $nestedLevel) {
                    $hierarchy['levels'][] = $nestedLevel;
                }
            }
        }

        array_unshift($hierarchy['levels'], $rootLevel);

        return $hierarchy;
    }

    /**
     * Parse individual schema property.
     */
    protected function parseProperty(string $name, array $definition, int $currentLevel): array
    {
        $type        = $definition['type']        ?? 'unknown';
        $description = $definition['description'] ?? '';

        $propertyInfo = [
            'name'          => $name,
            'type'          => $type,
            'description'   => $description,
            'level'         => $currentLevel,
            'nested_levels' => [],
        ];

        if ($type === 'object' || (is_array($type) && in_array('object', $type))) {
            $nestedProperties = $definition['properties'] ?? [];
            $nestedLevel      = [
                'level'           => $currentLevel + 1,
                'parent_property' => $name,
                'properties'      => [],
            ];

            foreach ($nestedProperties as $nestedName => $nestedDef) {
                $nestedLevel['properties'][] = $this->parseProperty($nestedName, $nestedDef, $currentLevel + 1);
            }

            $propertyInfo['nested_levels'][] = $nestedLevel;
        } elseif ($type === 'array' || (is_array($type) && in_array('array', $type))) {
            $items = $definition['items'] ?? [];
            if (isset($items['type']) && ($items['type'] === 'object' || (is_array($items['type']) && in_array('object', $items['type'])))) {
                $nestedProperties = $items['properties'] ?? [];
                $nestedLevel      = [
                    'level'           => $currentLevel + 1,
                    'parent_property' => $name,
                    'is_array'        => true,
                    'properties'      => [],
                ];

                foreach ($nestedProperties as $nestedName => $nestedDef) {
                    $nestedLevel['properties'][] = $this->parseProperty($nestedName, $nestedDef, $currentLevel + 1);
                }

                $propertyInfo['nested_levels'][] = $nestedLevel;
            }
        }

        return $propertyInfo;
    }

    /**
     * Extract all data points from schema for validation.
     */
    public function extractDataPoints(SchemaDefinition $schemaDefinition): array
    {
        $schema     = $schemaDefinition->schema;
        $properties = $schema['properties'] ?? [];

        return $this->extractDataPointsFromProperties($properties);
    }

    /**
     * Recursively extract data points from schema properties.
     */
    public function extractDataPointsFromProperties(array $properties, string $prefix = ''): array
    {
        $dataPoints = [];

        foreach ($properties as $name => $definition) {
            $path = $prefix ? "$prefix.$name" : $name;
            $type = $definition['type'] ?? 'unknown';

            if ($type === 'object' || (is_array($type) && in_array('object', $type))) {
                $nestedProperties = $definition['properties'] ?? [];
                $dataPoints       = array_merge(
                    $dataPoints,
                    $this->extractDataPointsFromProperties($nestedProperties, $path)
                );
            } elseif ($type === 'array' || (is_array($type) && in_array('array', $type))) {
                $items = $definition['items'] ?? [];
                if (isset($items['properties'])) {
                    $dataPoints = array_merge(
                        $dataPoints,
                        $this->extractDataPointsFromProperties($items['properties'], $path)
                    );
                } else {
                    $dataPoints[] = $path;
                }
            } else {
                $dataPoints[] = $path;
            }
        }

        return $dataPoints;
    }

    /**
     * Extract data point paths from fragment selector.
     */
    public function extractDataPointsFromFragmentSelector(array $fragmentSelector, string $prefix = ''): array
    {
        $dataPoints = [];

        foreach ($fragmentSelector['children'] ?? [] as $key => $child) {
            $path = $prefix ? "$prefix.$key" : $key;

            if (isset($child['children']) && !empty($child['children'])) {
                $dataPoints = array_merge(
                    $dataPoints,
                    $this->extractDataPointsFromFragmentSelector($child, $path)
                );
            } else {
                $dataPoints[] = $path;
            }
        }

        return $dataPoints;
    }
}
