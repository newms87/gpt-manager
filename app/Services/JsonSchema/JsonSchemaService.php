<?php

namespace App\Services\JsonSchema;

use Exception;

class JsonSchemaService
{
    /** @var bool  Whether to use citations for the schema. NOTE: This will modify the output so that */
    protected bool $useCitations = false;
    /** @var bool  Make sure each object includes the ID property */
    protected bool $useId = false;
    /** @var bool  Make sure each object includes the name property */
    protected bool $requireName = false;

    static array $idDef = [
        'type'        => ['number', 'null'],
        'description' => 'Set the ID if the value was derived from the DB (ie: teamObjects). Otherwise this should be null. NOTE: You can update the value in the DB by providing the ID and a new value.',
    ];

    static array $saveDef = [
        'type'        => 'boolean',
        'description' => 'Set to true if the value was NOT derived from the DB (ie: teamObjects). The value will be created or updated for the attribute in the DB. If set to false, citation must be null',
    ];

    static array $citationDef = [
        'type'                 => ['object', 'null'],
        'description'          => <<<TEXT
Set citation to null if:
  * The value was explicitly set to null.
  * The value was derived directly from the database (e.g., teamObjects).
  * The value matches what is already stored in the database.

When save is false:
  * citation must always be null.

If the value originates from a message or a URL and provides a better (and different) answer than the database value:
  * Set citation to reference the source(s) and set save to true.
  * ALWAYS defer to the DB and set citation to null unless you are very confident the new value is better than the original.
TEXT
        ,
        'properties'           => [
            'date'       => [
                'type'        => ['string', 'null'],
                'description' => "The date (yyyy-mm-dd) and time (00:00:00 if time n/a) of the attribute's value (ONLY if data changes over time, otherwise null). ONLY set this value if it makes sense to plot in a time series! Leave null for names, descriptions, static properties, etc. Only set date if there is an obvious date related to the value. Format should always be full date (and time if available). NO PARTIAL DATES!",
            ],
            'confidence' => [
                'type'        => 'string',
                'description' => 'The confidence level of the attribute value. Must be one of the following: "High", "Medium", "Low", "" (empty string). Leave blank if property value is null',
            ],
            'reason'     => [
                'type'        => 'string',
                'description' => 'A brief explanation of why the value was chosen and why the confidence level was set to what it was. Leave blank if property value is null',
            ],
            'sources'    => [
                'type'        => 'array',
                'description' => 'The source of the attribute value. Cite any URLs, Messages IDs from the thread or files. Make sure you include all relevant URLs, Message IDs, or file IDs that contain the value (more than 1 source if others are applicable)',
                'items'       => [
                    'anyOf' => [
                        [
                            'type'                 => 'object',
                            'properties'           => [
                                'url'         => [
                                    'type'        => 'string',
                                    'description' => 'A URL that contains the chosen value. If an image/file is provided, use the URL given in image_url (DO NOT USE THE FILENAME)',
                                ],
                                'location'    => [
                                    'type'        => 'string',
                                    'description' => 'A value such as "In the first paragraph", "In the title", "In the image caption", "Page 2", etc. to describe where in the source content the attribute was derived. NEVER SET THIS TO ACTUAL CONTENT OF THE SOURCE JUST A QUICK HINT OF WHERE TO LOOK IN THE SOURCE. If source content is very short (ie 1 paragraph, etc) just leave this blank.',
                                ],
                                'explanation' => [
                                    'type'        => 'string',
                                    'description' => 'A brief explanation of how you know this source contains the value',
                                ],
                            ],
                            'additionalProperties' => false,
                            'required'             => ['url', 'location', 'explanation'],
                        ],
                        [
                            'type'                 => 'object',
                            'properties'           => [
                                'message_id'  => [
                                    'type'        => 'string',
                                    'description' => "A message ID that the attribute was sourced from. If a user message is wrapped with <AgentMessage id='message_id'>...</AgentMessage>, it contains info leading you to the answer you gave for the attribute, provide the message ID as a source. If no <AgentMessage> tags are present, omit this field",
                                ],
                                'location'    => [
                                    'type'        => 'string',
                                    'description' => 'A value such as "First paragraph", "In Title", "Towards the end", etc. to describe where in the source content the attribute was derived. NEVER SET THIS TO ACTUAL CONTENT OF THE SOURCE JUST A QUICK HINT OF WHERE TO LOOK IN THE SOURCE. If source content is very short (ie 1 paragraph, etc) just leave this blank.',
                                ],
                                'explanation' => [
                                    'type'        => 'string',
                                    'description' => 'A brief explanation of how you know this source contains the value',
                                ],
                            ],
                            'additionalProperties' => false,
                            'required'             => ['message_id', 'location', 'explanation'],
                        ],
                    ],
                ],
            ],
        ],
        'additionalProperties' => false,
        'required'             => ['date', 'confidence', 'reason', 'sources'],
    ];

    public function useId(bool $useId = true): self
    {
        $this->useId = $useId;

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
            $formattedItem = $this->formatAndCleanSchemaItem($childName, $value, $depth, $key === 'name');
            if ($formattedItem) {
                $formattedSchema[$key] = $formattedItem;
            }
        }

        if ($this->requireName && !isset($formattedSchema['name'])) {
            $formattedSchema['name'] = [
                'type' => 'string',
            ];
        }

        if ($this->useId) {
            $formattedSchema['id'] = [
                '$ref' => '#/$defs/id',
            ];
        }

        if ($depth > 0) {
            return $formattedSchema;
        }

        return $this->formatRootSchemaObject($name, $schema, $formattedSchema);
    }

    /**
     * Format the root schema object with the required properties and definitions
     */
    public function formatRootSchemaObject($name, $schema, $formattedSchema): array
    {
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

        if ($this->useCitations) {
            $formattedSchema['$defs']['citation'] = static::$citationDef;
        }

        if ($this->useId) {
            $formattedSchema['$defs']['id']   = static::$idDef;
            $formattedSchema['$defs']['save'] = static::$saveDef;
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
                'type'                 => $type,
                'properties'           => $this->formatAndCleanSchema("$name.properties", $properties, $depth + 1),
                'additionalProperties' => false,
            ],
            'array' => [
                'type'  => $type,
                'items' => $this->formatAndCleanSchemaItem("$name.items", $items, $depth + 1, $required),
            ],
            'string', 'number', 'integer', 'boolean', 'null' => $this->getAttributeSchema($typeList),
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

    /**
     * Get the attribute schema for a property including optionally adding citations and IDs.
     *
     * If citations are enabled, the schema will require a citation object with a date, confidence, reason, and sources.
     * If IDs are enabled, the schema will require an ID property.
     * If neither are enabled, the schema will only require the value using the value's type as the schema.
     */
    public function getAttributeSchema($type): array
    {
        if (!$this->useCitations && !$this->useId) {
            return ['type' => $type];
        }

        $schema = [
            'type'                 => 'object',
            'properties'           => [
                'value' => ['type' => $type],
            ],
            'additionalProperties' => false,
            'required'             => ['value'],
        ];

        if ($this->useCitations) {
            $schema['properties']['citation'] = ['$ref' => '#/$defs/citation'];
            $schema['required'][]             = 'citation';
        }

        // Indicate that this property
        if ($this->useId) {
            $schema['properties']['save'] = ['$ref' => '#/$defs/save'];
            $schema['required'][]         = 'save';
        }

        return $schema;
    }
}
