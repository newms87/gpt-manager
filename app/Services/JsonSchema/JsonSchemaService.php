<?php

namespace App\Services\JsonSchema;

use Exception;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Helpers\FileHelper;
use Str;

class JsonSchemaService
{
    /** @var bool When strict mode is enabled, certain type castings will not be allowed (ie: scalar => array/object or object/array => scalar) */
    protected bool $isStrict = false;

    /** @var bool Whether to use citations for the schema. NOTE: This will modify the output so that */
    protected bool $useCitations = false;

    /** @var bool Make sure each object includes the ID property */
    protected bool $useId = false;

    /** @var bool Include the property_meta definition */
    protected bool $usePropertyMeta = false;

    /** @var bool Make sure each object includes the name property */
    protected bool $requireName = false;

    /** @var bool Automatically add null types for property values (except for id / name of objects) */
    protected bool $includeNullValues = false;

    /** @var bool Include a meta.name field in the output to label the artifact */
    protected bool $useArtifactMeta = false;

    public static array $idCreateOrUpdateDef = [
        'type'        => ['number', 'null'],
        'description' => 'ID must match an object from json_content with the same `type` (ie: the `title` property is an alias for `type` as it may appear as either). If no such object exists, set `id: null`',
    ];

    public static array $idCreateDef = [
        'type'        => 'null',
        'description' => 'Set ID to null, this indicates no existing record and a new record should be created',
    ];

    public static array $idUpdateDef = [
        'type'        => 'number',
        'description' => 'ID must match an object from json_content with the same `type` (ie: the `title` property is an alias for `type` as it may appear as either).',
    ];

    public static string $nameOptionalDescription = 'If creating a new record and setting ID to null, the name is required. Otherwise set name to null (ie: just updating an existing record and giving an existing record ID)';

    protected array $artifactMetaDef = [];

    protected array $propertyMetaDef = [];

    public function getConfig(): array
    {
        return [
            'useId'             => $this->useId,
            'requireName'       => $this->requireName,
            'includeNullValues' => $this->includeNullValues,
            'useCitations'      => $this->useCitations,
            'usePropertyMeta'   => $this->usePropertyMeta,
            'useArtifactMeta'   => $this->useArtifactMeta,
        ];
    }

    public function setConfig(?array $config = []): static
    {
        $config = $config ?: [];

        $this->useId             = $config['useId']             ?? $this->useId;
        $this->requireName       = $config['requireName']       ?? $this->requireName;
        $this->includeNullValues = $config['includeNullValues'] ?? $this->includeNullValues;
        $this->useCitations      = $config['useCitations']      ?? $this->useCitations;
        $this->usePropertyMeta   = $config['usePropertyMeta']   ?? $this->usePropertyMeta;
        $this->useArtifactMeta   = $config['useArtifactMeta']   ?? $this->useArtifactMeta;

        return $this;
    }

    public function isUsingDbFields(): bool
    {
        return $this->useId && $this->requireName;
    }

    public function isUsingCitations(): bool
    {
        return $this->useCitations;
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

    public function includeNullValues(bool $include = true): self
    {
        $this->includeNullValues = $include;

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

    public function useArtifactMeta(bool $include = true): self
    {
        $this->useArtifactMeta = $include;

        return $this;
    }

    public function getArtifactMeta(): array
    {
        if (!$this->artifactMetaDef) {
            // Inject the property meta definition into the schema
            $this->artifactMetaDef = FileHelper::parseYamlFile(app_path('Services/JsonSchema/artifact_meta.def.yaml'));
        }

        return $this->artifactMetaDef;
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
     * Convert a fragment selector to a dot notation string
     */
    public function fragmentSelectorToDot(array $fragmentSelector): string
    {
        $dot = '';

        if (!empty($fragmentSelector['children'])) {
            $childDots = [];
            foreach ($fragmentSelector['children'] as $key => $child) {
                $childDot = $key;
                if (!empty($child['children'])) {
                    $nestedDot = $this->fragmentSelectorToDot($child);

                    if ($nestedDot) {
                        $childDot .= '.' . $nestedDot;
                    }
                }
                $childDots[] = $childDot;
            }

            if (count($childDots) > 1) {
                $dot .= '(' . implode('|', $childDots) . ')';
            } else {
                $dot .= $childDots[0];
            }
        }

        return $dot;
    }

    /**
     * Recursively filters a JSON schema using a fragment selector to select a subset of properties
     */
    public function applyFragmentSelector(array $schema, ?array $fragmentSelector = null): array
    {
        if (!$fragmentSelector) {
            return $schema;
        }
        // The list of allowed properties to be included in the schema
        $filteredProperties = [];

        $children = $fragmentSelector['children'] ?? [];

        // If create object is enabled, then add a null ID to the schema
        $canCreate = $fragmentSelector['create'] ?? false;
        $canUpdate = $fragmentSelector['update'] ?? false;

        $nameDescription = 'Name of the ' . ($schema['title'] ?? 'Object');

        // Set the ID field based on if the schema is allowing creation / updating of an existing object
        if ($canCreate && $canUpdate) {
            $filteredProperties['id']   = static::$idCreateOrUpdateDef;
            $filteredProperties['name'] = ['type' => ['string', 'null'], 'description' => $nameDescription . '. ' . static::$nameOptionalDescription];
        } elseif ($canUpdate) {
            $filteredProperties['id'] = static::$idUpdateDef;
        } elseif ($canCreate) {
            $filteredProperties['id'] = static::$idCreateDef;
            // In the case of creation, the name is required, so set that here (can be overridden by the 'name' property in the fragment selector)
            $filteredProperties['name'] = ['type' => 'string', 'description' => $nameDescription];
        }

        // The total set of properties defined in the JSON Schema for this object
        $properties = $schema['properties'] ?? [];

        foreach ($children as $selectedKey => $selectedProperty) {
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
                    $filteredProperties[$selectedKey] = [
                        ...$schemaProperty,
                        'type'  => 'array',
                        'items' => $result,
                    ];
                } else {
                    $filteredProperties[$selectedKey] = $result;
                }
            }
        }

        // If there were no properties selected, then return an empty array
        if (!$filteredProperties) {
            return [];
        }

        // Take advantage of copy on write to avoid modifying the original schema, and return the updated schema with filtered properties
        $schema['properties'] = $filteredProperties;

        return $schema;
    }

    /**
     * Flatten the data using a fragment selector to select a subset of properties
     */
    public function flattenByFragmentSelector(array $data, array $fragmentSelector): array
    {
        $filteredData = $this->filterDataByFragmentSelector($data, $fragmentSelector);

        return collect($filteredData)->flatten()->toArray();
    }

    /**
     * Recursively get the leaf types of a fragment selector
     */
    public function getFragmentSelectorLeafTypes(array $fragmentSelector): array
    {
        if (empty($fragmentSelector['children'])) {
            return (array)$fragmentSelector['type'] ?? [];
        }

        $types = [];
        foreach ($fragmentSelector['children'] as $child) {
            $types = array_merge($types, $this->getFragmentSelectorLeafTypes($child));
        }

        return array_unique($types);
    }

    /**
     * Recursively filters data using a fragment selector to select a subset of properties
     */
    public function filterDataByFragmentSelector(array $data, ?array $fragmentSelector = null): array
    {
        if (!$fragmentSelector) {
            return $data;
        }

        if (empty($fragmentSelector['children'])) {
            return [];
        }

        if (array_key_exists('strict', $fragmentSelector)) {
            $this->isStrict = (bool)$fragmentSelector['strict'];
        }

        $filtered = [];

        // Special case for handling an array of objects at the top level, instead of an object w/ associative keys
        if (!is_associative_array($data)) {
            foreach ($data as $datum) {
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

        foreach ($fragmentSelector['children'] as $selectedKey => $selectedProperty) {
            if (empty($selectedProperty['type'])) {
                throw new Exception("Fragment selector must have a type: $selectedKey: " . json_encode($selectedProperty));
            }

            $dataProperty = $data[$selectedKey] ?? null;

            // Skip if the property is not in the data
            // NOTE: We do not throw an error here because fragments are not directly tied to schemas. They are loosely correlated, but schemas may change while selections remain the same.
            if ($dataProperty === null) {
                continue;
            }

            $isObjectOrArray = in_array($selectedProperty['type'], ['object', 'array']);

            if (is_scalar($dataProperty)) {
                // If the data is a scalar type, but the fragment selector is looking for an object or an array, then handle this scenario
                if ($isObjectOrArray) {
                    if ($this->isStrict) {
                        throw new ValidationError("Fragment selector type mismatch: $selectedKey: selected fragment specified a $selectedProperty[type] (an array of object type), but found " . gettype($dataProperty) . ' (a scalar type) instead');
                    }
                }
                $filtered[$selectedKey] = $dataProperty;
            } else {
                // If the data is an object or an array, but the fragment selector is looking for a scalar type, then handle this scenario
                if (!$isObjectOrArray) {
                    if ($this->isStrict) {
                        throw new ValidationError("Fragment selector type mismatch: $selectedKey: selected fragment specified a $selectedProperty[type] (a scalar type), but found " . gettype($dataProperty) . ' (an array of object type) instead');
                    }
                    $filtered[$selectedKey] = json_encode($dataProperty);
                } else {
                    if (is_associative_array($dataProperty)) {
                        $result = $this->filterDataByFragmentSelector($dataProperty, $selectedProperty);
                    } else {
                        $result = array_map(fn($item) => is_array($item) ? $this->filterDataByFragmentSelector($item, $selectedProperty) : $item, $dataProperty);
                    }

                    if ($result) {
                        $filtered[$selectedKey] = $result;
                    }
                }
            }
        }

        return $filtered;
    }

    /**
     * Format and clean the schema to match the requirements of a strict JSON schema
     *
     * @param  string  $name  The name for this version of the schema
     * @param  array  $schema  The schema to format. The schema should be properly formatted JSON schema (starting
     *                         with properties of an object as they will be nested inside the main schema)
     *                         each scalar property is transformed into a citation object
     * @param  int  $depth  The depth of the schema (used for recursion)
     *
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
        foreach ($properties as $key => $value) {
            $childName = $name . '.' . $key;
            // Id and name are special keys that will have their types explicitly set to null when needed. All other fields should be allowed to be null
            $formattedItem = $this->formatAndCleanSchemaItem($childName, $value, $depth, !in_array($key, ['id', 'name']));
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
        if ($this->useArtifactMeta) {
            $propertiesSchema['__meta'] = $this->getArtifactMeta();
        }

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
            $formattedSchema['$defs']['id'] = static::$idCreateOrUpdateDef;
        }

        if ($this->usePropertyMeta) {
            $propertyMeta                              = $this->getPropertyMeta();
            $formattedSchema['$defs']['property_meta'] = $propertyMeta;

            // collapse all defs up to top level
            $formattedSchema['$defs'] += $propertyMeta['$defs'];
            unset($propertyMeta['$defs']);
        }

        // Preserve any $defs from the original schema (e.g., pageSource, stringSearch, dateSearch)
        // Use array union so internal definitions (id, property_meta) take precedence over input schema defs
        if (isset($schema['$defs'])) {
            $formattedSchema['$defs'] = ($formattedSchema['$defs'] ?? []) + $schema['$defs'];
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
    public function formatAndCleanSchemaItem($name, $value, $depth = 0, $allowNull = true): ?array
    {
        // Pass through $ref entries unchanged - they reference definitions in $defs
        if (isset($value['$ref'])) {
            return $value;
        }

        $type        = $value['type']        ?? null;
        $title       = $value['title']       ?? null;
        $description = $value['description'] ?? null;
        $enum        = $value['enum']        ?? null;
        $properties  = $value['properties']  ?? [];
        $items       = $value['items']       ?? [];

        $hasNull  = false;
        $mainType = $type;

        if (is_array($type)) {
            $mainType = null;
            foreach ($type as $typeName) {
                if ($typeName === 'null') {
                    $hasNull = true;
                } elseif (!$mainType) {
                    $mainType = $typeName;
                }
            }
        }

        $isArray  = $mainType === 'array';
        $isObject = $mainType === 'object';

        // The type should always be allowed to be null except when it is a required field
        $typeList = $type;

        if ($allowNull && !$hasNull && $this->includeNullValues) {
            if (!is_array($typeList)) {
                $typeList = [$typeList];
            }
            $typeList[] = 'null';
        }

        $item = match ($mainType) {
            'object' => [
                'type'                 => $typeList,
                'properties'           => $this->formatAndCleanSchema("$name.properties", $properties, $depth + 1),
                'additionalProperties' => false,
            ],
            'array' => [
                'type'  => $typeList,
                'items' => $this->formatAndCleanSchemaItem("$name.items", $items, $depth + 1, $allowNull),
            ],
            'string', 'number', 'integer', 'boolean', 'null' => ['type' => $typeList],
            default => throw new Exception("Unknown type at path $name: " . ($mainType ?: '(empty)')),
        };

        // If the type is an object with no properties, it is an empty object and can be ignored
        if ($isArray  && !$item['items'] ||
            $isObject && !$item['properties']) {
            return null;
        }

        if ($isObject) {
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
    public function formatAndFilterSchema(string $name, array $schema, ?array $fragmentSelector = null): array|string
    {
        if ($fragmentSelector) {
            $schema = $this->applyFragmentSelector($schema, $fragmentSelector);
        }

        // Name the response schema result based on the given name and a hash of the schema after applying fragment selector
        $name = $name . '-' . substr(md5(json_encode($schema)), 0, 7);

        return $this->formatAndCleanSchema(Str::slug($name), $schema);
    }

    /**
     * Identify the objects in the hierarchy that do not have the id field set
     * and populate the ID in the $jsonContent using the ID (if available) in the $referenceJsonContent
     */
    public function hydrateIdsInJsonContent(array $jsonContent, ?array $referenceJsonContent): array
    {
        if (!$referenceJsonContent || !$jsonContent) {
            return [];
        }

        if (!array_key_exists('id', $jsonContent) && array_key_exists('id', $referenceJsonContent)) {
            $jsonContent['id'] = $referenceJsonContent['id'];
        }

        foreach ($jsonContent as $propertyKey => $propertyValue) {
            $referencePropertyValue = $referenceJsonContent[$propertyKey] ?? null;

            // Skip if the property is not in the data
            if (!$propertyValue || !is_array($propertyValue) || !$referencePropertyValue || !is_array($referencePropertyValue)) {
                continue;
            }

            if (is_associative_array($propertyValue)) {

                // If our object is an associate array, but the reference is not, then we need to get the first value from the reference and assume it will match the main object structure
                if (!is_associative_array($referencePropertyValue)) {
                    $referencePropertyValue = reset($referencePropertyValue);
                }

                $jsonContent[$propertyKey] = $this->hydrateIdsInJsonContent($propertyValue, $referencePropertyValue);
            } else {
                // Make the reference property into a list if it is not already so we match the structure of the main object
                if (is_associative_array($referencePropertyValue)) {
                    $referencePropertyValue = [$referencePropertyValue];
                }

                $mappedItems = [];

                foreach ($propertyValue as $index => $item) {
                    $mappedItems[$index] = $this->hydrateIdsInJsonContent($item, $referencePropertyValue[$index] ?? null);
                }

                $jsonContent[$propertyKey] = $mappedItems;
            }
        }

        return $jsonContent;
    }
}
