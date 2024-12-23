<?php

namespace App\Services\JsonSchema;

use Exception;

class JsonSchemaService
{
    /** @var bool  Whether to use citations for the schema. NOTE: This will modify the output so that */
    protected bool $useCitations = false;
    /** @var bool  Make sure each object includes the ID property */
    protected bool $requireId = false;
    /** @var bool  Make sure each object includes the name property */
    protected bool $requireName = false;

    static array $citedValue = [
        'type'       => 'object',
        'properties' => [
            'date'       => [
                'type'        => 'string',
                'description' => "The date (yyyy-mm-dd) and time (00:00:00 if time n/a) of the attribute's value (if data changes over time). Format should always be full date (no partial dates)!",
            ],
            'confidence' => [
                'type'        => 'string',
                'description' => 'The confidence level of the attribute value. Must be one of the following: "High", "Medium", "Low"',
            ],
            'reason'     => [
                'type'        => 'string',
                'description' => 'A brief explanation of why the value was chosen and why the confidence level was set to what it was',
            ],
            'value'      => [
                'type' => ['string', 'number', 'boolean'],
            ],
            'sources'    => [
                'type'        => 'array',
                'description' => 'The source of the attribute value. Cite any URLs, Messages IDs from the thread or files. Make sure you include all relevant URLs, Message IDs, or file IDs that contain the value (more than 1 source if others are applicable)',
                'items'       => [
                    'anyOf' => [
                        [
                            'type'       => 'object',
                            'properties' => [
                                'url'         => [
                                    'type'        => 'string',
                                    'description' => 'A URL the contains the chosen value',
                                ],
                                'explanation' => [
                                    'type'        => 'string',
                                    'description' => 'A brief explanation of how you know this source contains the value',
                                ],
                            ],
                        ],
                        [
                            'type'       => 'object',
                            'properties' => [
                                'message_id'  => [
                                    'type'        => 'string',
                                    'description' => "A message ID that the attribute was sourced from. If a user message is wrapped with <AgentMessage id='message_id'>...</AgentMessage>, it contains info leading you to the answer you gave for the attribute, provide the message ID as a source. If no <AgentMessage> tags are present, omit this field",
                                ],
                                'explanation' => [
                                    'type'        => 'string',
                                    'description' => 'A brief explanation of how you know this source contains the value',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    public function requireId(bool $requireId = true): self
    {
        $this->requireId = $requireId;

        return $this;
    }

    public function requireName(bool $requireName = true): self
    {
        $this->requireName = $requireName;

        return $this;
    }

    public function useCitations(bool $useCitations = true): self
    {
        $this->useCitations = $useCitations;

        return $this;
    }

    /**
     * Recursively filters a JSON schema by a sub-selection of properties
     */
    public function filterSchemaBySubSelection(array $schema, array $subSelection = null): array
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
                $result = $this->filterSchemaBySubSelection($properties[$selectedKey], $selectedProperty);
            } elseif ($selectedProperty['type'] === 'array') {
                $result = $this->filterSchemaBySubSelection($properties[$selectedKey]['items'], $selectedProperty);
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

        $formattedSchema = [];

        // Resolve the properties of the schema. It's possible the schema is an array of properties already, so just parse from the array instead.
        $properties = $schema['properties'] ?? $schema;

        // Ensures all properties (and sub properties) are both required and have no additional properties. It does this recursively.
        foreach($properties as $key => $value) {
            $childName     = $name . '.' . $key;
            $formattedItem = $this->formatAndCleanSchemaItem($childName, $value, $depth);
            if ($formattedItem) {
                $formattedSchema[$key] = $formattedItem;
            }
        }

        if ($this->requireName && !isset($formattedSchema['name'])) {
            $formattedSchema['name'] = [
                'type' => 'string',
            ];
        }

        if ($this->requireId && !isset($formattedSchema['id'])) {
            $formattedSchema['id'] = [
                'type' => ['number', 'null'],
            ];
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
    public function formatAndCleanSchemaItem($name, $value, $depth = 0): array|null
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
                'properties'           => $this->formatAndCleanSchema("$name.properties", $properties, $depth + 1),
                'additionalProperties' => false,
            ],
            'array' => [
                'type'  => $type,
                'items' => $this->formatAndCleanSchemaItem("$name.items", $items, $depth + 1),
            ],
            'string', 'number', 'integer', 'boolean', 'null' => $this->useCitations ? static::$citedValue : ['type' => $type],
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
