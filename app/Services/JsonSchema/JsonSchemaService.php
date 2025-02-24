<?php

namespace App\Services\JsonSchema;

use Exception;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Helpers\FileHelper;
use Str;

class JsonSchemaService
{
    /** @var bool  Whether to use citations for the schema. NOTE: This will modify the output so that */
    protected bool $useCitations = false;
    /** @var bool  Make sure each object includes the ID property */
    protected bool $useId = false;
    /** @var bool  Include the property_meta definition */
    protected bool $usePropertyMeta = false;
    /** @var bool  Make sure each object includes the name property */
    protected bool $requireName = false;

    static array $idDef = [
        'type'        => ['number', 'null'],
        'description' => 'ID must match an object from json_content with the same `type` (ie: the `title` property means `type` in this context). If no such object exists, set `id: null`',
    ];

    protected array $propertyMetaDef = [];

    public function isUsingDbFields(): bool
    {
        return $this->useId && $this->requireName;
    }

    public function useDbFields(bool $use = true): self
    {
        return $this->useId($use)->requireName($use);
    }

    public function useId(bool $use = true): self
    {
        $this->useId = $use;

        return $this;
    }

    public function requireName(bool $require = true): self
    {
        $this->requireName = $require;

        return $this;
    }

    public function useCitations(bool $use = true): self
    {
        $this->useCitations = $use;

        if ($use) {
            $this->usePropertyMeta = true;
        }

        return $this;
    }

    public function usePropertyMeta(bool $use = true): self
    {
        $this->usePropertyMeta = $use;

        return $this;
    }

    /**
     * Get the property meta definition for the schema
     */
    public function getPropertyMeta(): array
    {
        if (!$this->propertyMetaDef) {
            // Inject the property meta definition into the schema
            $this->propertyMetaDef = FileHelper::parseYamlFile(app_path('Services/JsonSchema/property_meta.def.yaml'));

            // If citations are not required, then remove the citation property of the property meta def
            if (!$this->useCitations) {
                unset($this->propertyMetaDef['properties']['citation']);
            }
        }

        return $this->propertyMetaDef;
    }

    /**
     * Recursively filters a JSON schema using a fragment selector to select a subset of properties
     */
    public function applyFragmentSelector(array $schema, array $fragmentSelector = null): array
    {
        if (!$fragmentSelector) {
            return $schema;
        }

        if (empty($fragmentSelector['children'])) {
            return [];
        }

        $properties = $schema['properties'] ?? [];
        $filtered   = [];

        foreach($fragmentSelector['children'] as $selectedKey => $selectedProperty) {
            if (empty($selectedProperty['type'])) {
                throw new Exception("Fragment selector must have a type: $selectedKey: " . json_encode($selectedProperty));
            }

            $schemaProperty = $properties[$selectedKey] ?? null;

            // Skip if the property is not in the schema
            // NOTE: We do not throw an error here because fragments are not directly tied to schemas. They are loosely correlated, but schemas may change while selections remain the same.
            if (!$schemaProperty) {
                continue;
            }

            if (!empty($schemaProperty['type']) && $schemaProperty['type'] !== $selectedProperty['type']) {
                throw new Exception("Fragment selector type mismatch: $selectedKey: Schema Type $schemaProperty[type] is not $selectedProperty[type]");
            }

            if ($selectedProperty['type'] === 'object') {
                $result = $this->applyFragmentSelector($schemaProperty, $selectedProperty);
            } elseif ($selectedProperty['type'] === 'array') {
                $result = $this->applyFragmentSelector($schemaProperty['items'], $selectedProperty);
            } else {
                $result = $schemaProperty;
            }

            if ($result) {
                if ($selectedProperty['type'] === 'array') {
                    $filtered[$selectedKey] = [
                        ...$schemaProperty,
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
     * Recursively filters data using a fragment selector to select a subset of properties
     */
    public function filterDataByFragmentSelector(array $data, array $fragmentSelector = null): array
    {
        if (!$fragmentSelector) {
            return $data;
        }

        if (empty($fragmentSelector['children'])) {
            return [];
        }

        $filtered = [];

        // Special case for handling an array of objects at the top level, instead of an object w/ associative keys
        if (!is_associative_array($data)) {
            foreach($data as $datum) {
                if (is_array($datum)) {
                    $filtered[] = $this->filterDataByFragmentSelector($datum, $fragmentSelector);
                }
            }

            return $filtered;
        }

        // When using ID, the data is likely coming from a database so it should specify the ID and type for objects so it is clear where this data came from
        if ($this->useId) {
            $fragmentSelector['children']['id']   = ['type' => 'string'];
            $fragmentSelector['children']['type'] = ['type' => 'string'];
        }

        foreach($fragmentSelector['children'] as $selectedKey => $selectedProperty) {
            if (empty($selectedProperty['type'])) {
                throw new Exception("Fragment selector must have a type: $selectedKey: " . json_encode($selectedProperty));
            }

            $dataProperty = $data[$selectedKey] ?? null;

            // Skip if the property is not in the data
            // NOTE: We do not throw an error here because fragments are not directly tied to schemas. They are loosely correlated, but schemas may change while selections remain the same.
            if (!$dataProperty) {
                continue;
            }

            $isObjectOrArray = in_array($selectedProperty['type'], ['object', 'array']);

            if (is_scalar($dataProperty)) {
                if ($isObjectOrArray) {
                    throw new ValidationError("Fragment selector type mismatch: $selectedKey: selected fragment specified a $selectedProperty[type] (an array of object type), but found " . gettype($dataProperty) . " (a scalar type) instead");
                }
                $result = $dataProperty;
            } else {
                if (!$isObjectOrArray) {
                    throw new ValidationError("Fragment selector type mismatch: $selectedKey: selected fragment specified a $selectedProperty[type] (a scalar type), but found " . gettype($dataProperty) . " (an array of object type) instead");
                }
                if (is_associative_array($dataProperty)) {
                    $result = $this->filterDataByFragmentSelector($dataProperty, $selectedProperty);
                } else {
                    $result = array_map(fn($item) => is_array($item) ? $this->filterDataByFragmentSelector($item, $selectedProperty) : $item, $dataProperty);
                }
            }

            if ($result) {
                $filtered[$selectedKey] = $result;
            }
        }

        return $filtered;
    }

    /**
     * Format and clean the schema to match the requirements of a strict JSON schema
     *
     * @param string $name         The name for this version of the schema
     * @param array  $schema       The schema to format. The schema should be properly formatted JSON schema (starting
     *                             with properties of an object as they will be nested inside the main schema)
     *                             each scalar property is transformed into a citation object
     * @param int    $depth        The depth of the schema (used for recursion)
     * @return array
     * @throws Exception
     */
    public function formatAndCleanSchema(string $name, array $schema, int $depth = 0): array
    {
        if (!$schema) {
            return [];
        }

        $propertiesSchema = [];

        // Resolve the properties of the schema. It's possible the schema is an array of properties already, so just parse from the array instead.
        $properties = $schema['properties'] ?? $schema;

        // Ensures all properties (and sub properties) are both required and have no additional properties. It does this recursively.
        foreach($properties as $key => $value) {
            $childName     = $name . '.' . $key;
            $formattedItem = $this->formatAndCleanSchemaItem($childName, $value, $depth, $key === 'name');
            if ($formattedItem) {
                $propertiesSchema[$key] = $formattedItem;
            }
        }

        if ($this->requireName && !isset($propertiesSchema['name'])) {
            $propertiesSchema['name'] = [
                'type' => 'string',
            ];
        }

        if ($this->useId) {
            $propertiesSchema['id'] = [
                '$ref' => '#/$defs/id',
            ];
        }

        if ($this->usePropertyMeta) {
            $propertiesSchema['property_meta'] = [
                '$ref' => '#/$defs/property_meta',
            ];
        }

        if ($depth > 0) {
            return $propertiesSchema;
        }

        return $this->formatRootSchemaObject($name, $schema, $propertiesSchema);
    }

    /**
     * Format the root schema object with the required properties and definitions
     */
    public function formatRootSchemaObject($name, $schema, $propertiesSchema): array
    {
        $formattedSchema = [
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => array_keys($propertiesSchema),
            'properties'           => $propertiesSchema,
        ];


        if (array_key_exists('title', $schema)) {
            $formattedSchema['title'] = $schema['title'];
        }

        if (array_key_exists('description', $schema)) {
            $formattedSchema['description'] = $schema['description'];
        }

        if ($this->useId) {
            $formattedSchema['$defs']['id'] = static::$idDef;
        }

        if ($this->usePropertyMeta) {
            $propertyMeta                              = $this->getPropertyMeta();
            $formattedSchema['$defs']['property_meta'] = $propertyMeta;

            // collapse all defs up to top level
            $formattedSchema['$defs'] += $propertyMeta['$defs'];
            unset($propertyMeta['$defs']);
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
    public function formatAndCleanSchemaItem($name, $value, $depth = 0, $required = false): array|null
    {
        $type        = $value['type'] ?? null;
        $title       = $value['title'] ?? null;
        $description = $value['description'] ?? null;
        $enum        = $value['enum'] ?? null;
        $properties  = $value['properties'] ?? [];
        $items       = $value['items'] ?? [];

        $resolvedType = is_array($type) ? $type[0] : $type;

        // The type should always be allowed to be null except when it is a required field
        $typeList = $required ? $type : [$type, 'null'];

        $item = match ($resolvedType) {
            'object' => [
                'type'                 => $typeList,
                'properties'           => $this->formatAndCleanSchema("$name.properties", $properties, $depth + 1),
                'additionalProperties' => false,
            ],
            'array' => [
                'type'  => $typeList,
                'items' => $this->formatAndCleanSchemaItem("$name.items", $items, $depth + 1, $required),
            ],
            'string', 'number', 'integer', 'boolean', 'null' => ['type' => $typeList],
            default => throw new Exception("Unknown type at path $name: " . ($type ?: '(empty)')),
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

    /**
     * Format the JSON schema and generate a unique name based on the given name and the schema structure
     */
    public function formatAndFilterSchema(string $name, array $schema, array $fragmentSelector = null): array|string
    {
        if ($fragmentSelector) {
            $schema = $this->applyFragmentSelector($schema, $fragmentSelector);
        }

        // Name the response schema result based on the given name and a hash of the schema after applying fragment selector
        $name = $name . '-' . substr(md5(json_encode($schema)), 0, 7);

        return $this->formatAndCleanSchema(Str::slug($name), $schema);
    }
}
