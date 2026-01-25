<?php

namespace App\Services\JsonSchema;

use App\Models\Agent\AgentThreadRun;
use App\Models\Schema\SchemaDefinition;
use App\Models\TeamObject\TeamObject;
use App\Models\TeamObject\TeamObjectAttribute;
use App\Models\TeamObject\TeamObjectAttributeSource;
use App\Models\TeamObject\TeamObjectRelationship;
use Newms87\Danx\Traits\HasDebugLogging;
use Carbon\Carbon;
use Exception;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Helpers\FileHelper;
use Newms87\Danx\Helpers\LockHelper;
use Newms87\Danx\Helpers\ModelHelper;
use Newms87\Danx\Helpers\StringHelper;
use Newms87\Danx\Models\Utilities\StoredFile;
use Newms87\Danx\Repositories\FileRepository;
use Throwable;

class JSONSchemaDataToDatabaseMapper
{
    use HasDebugLogging;

    protected ?SchemaDefinition $schemaDefinition = null;

    protected ?TeamObject $rootObject = null;

    protected ?TeamObject $parentObject = null;

    public function setSchemaDefinition(?SchemaDefinition $schemaDefinition = null): static
    {
        $this->schemaDefinition = $schemaDefinition;

        return $this;
    }

    public function setRootObject(?TeamObject $rootObject = null): static
    {
        $this->rootObject = $rootObject;

        return $this;
    }

    public function setParentObject(?TeamObject $parentObject = null): static
    {
        $this->parentObject = $parentObject;

        return $this;
    }

    public function resolveTeamObject($type, $name, $input = []): ?TeamObject
    {
        if (!$type || !$name) {
            throw new ValidationError("Team Objects requires a type and name for each object: \n\nType: $type\nName: $name\nInput:\n" . json_encode($input));
        }

        $teamObjectQuery = TeamObject::where('type', $type)
            ->where('name', $name)
            ->withTrashed();

        // Add date to uniqueness check - same name with different dates are allowed
        $date = $input['date'] ?? null;
        if ($date) {
            // Normalize date to Y-m-d format for comparison
            try {
                $normalizedDate = Carbon::parse($date)->format('Y-m-d');
                $teamObjectQuery->whereDate('date', $normalizedDate);
            } catch (Exception) {
                // If date parsing fails, check for exact string match or null
                $teamObjectQuery->where('date', $date);
            }
        } else {
            $teamObjectQuery->whereNull('date');
        }

        if ($this->schemaDefinition) {
            $teamObjectQuery->where('schema_definition_id', $this->schemaDefinition->id);
        } else {
            $teamObjectQuery->whereNull('schema_definition_id');
        }

        if ($this->rootObject) {
            $teamObjectQuery->where('root_object_id', $this->rootObject->id);
        } else {
            $teamObjectQuery->whereNull('root_object_id');
        }

        // Filter by parent relationship for level 2+ objects
        // This ensures objects under different parents (but same root) are not incorrectly flagged as duplicates
        if ($this->parentObject) {
            $teamObjectQuery->whereHas('relatedToMe', function ($relQuery) {
                $relQuery->where('team_object_id', $this->parentObject->id);
            });
        }

        return $teamObjectQuery->first();
    }

    public function acquireCreateLock($type, $name): void
    {
        LockHelper::acquire("create-team-object-$type-$name");
    }

    public function releaseCreateLock($type, $name): void
    {
        LockHelper::release("create-team-object-$type-$name");
    }

    /**
     * Create a new Team Object record based on type, name and input
     */
    public function createTeamObject($type, $name, $input = [], $orUpdate = false): TeamObject
    {
        $this->acquireCreateLock($type, $name);

        try {
            $teamObject = $this->resolveTeamObject($type, $name, $input);

            if ($teamObject) {
                // If this object was deleted, then restore the object so we can re-use the deleted object instead of creating a new one
                if ($teamObject->deleted_at) {
                    $teamObject->restore();
                } elseif (!$orUpdate) {
                    $dateStr = $teamObject->date?->format('Y-m-d') ?? 'null';
                    throw new ValidationError(
                        "Team Object of type $type already exists: name='$name', date='$dateStr', " .
                        'root_object_id=' . ($this->rootObject?->id ?? 'null') . ', ' .
                        'parent_object_id=' . ($this->parentObject?->id ?? 'null') . ', ' .
                        'schema_definition_id=' . ($this->schemaDefinition?->id ?? 'null')
                    );
                }
            } else {
                $teamObject = TeamObject::make([
                    'team_id'              => team()->id,
                    'type'                 => $type,
                    'name'                 => $name,
                    'root_object_id'       => $this->rootObject?->id,
                    'schema_definition_id' => $this->schemaDefinition?->id,
                ]);
            }

            return $this->updateTeamObject($teamObject, $input);
        } finally {
            $this->releaseCreateLock($type, $name);
        }
    }

    /**
     * Update an existing team object
     */
    public function updateTeamObject(TeamObject $teamObject, $input = []): TeamObject
    {
        $fillableProps = ['name', 'date', 'description', 'url'];

        // Sometimes a user will choose to explicitly set these object properties as attributes to the object. In this case we want to convert from an object attribute to a string property so we can save it directly on the object.
        // NOTE: The attribute will still be saved as an object attribute related to the object, so this is duplicated information, but easier to access directly on the object, instead of as an object attribute.
        foreach ($fillableProps as $propName) {
            if (!empty($input[$propName]) && is_array($input[$propName])) {
                $input[$propName] = $input[$propName]['value'] ?? null;
            }

            if (array_key_exists($propName, $input) && ($input[$propName] === null || $input[$propName] === '')) {
                unset($input[$propName]);
            }
        }

        // Cleanup date inputs in case they are in an unexpected format
        if (!empty($input['date'])) {
            try {
                $input['date'] = carbon($input['date']);
            } catch (Exception) {
                // If the date cannot be parsed (e.g., LLM returned "unknown"), remove it
                unset($input['date']);
            }
        }

        try {
            $teamObject->fill($input)->validate();
        } catch (ValidationError $exception) {
            // If there is a naming conflict, then resolve the name
            if ($exception->getCode() === 409) {
                $teamObject->name = ModelHelper::getNextModelName($teamObject);
            }
        }

        $teamObject->save();

        return $teamObject;
    }

    /**
     * Create or Update the value, confidence and sources for a Team Object Attribute record based on team object
     * and property name
     */
    public function saveTeamObjectAttribute(TeamObject $teamObject, $name, $attribute, ?array $meta = []): ?TeamObjectAttribute
    {
        if (!$name) {
            throw new Exception('Save Team Object Attribute requires a name');
        }

        $value = $attribute['value'] ?? null;

        $propertyMeta = collect($meta)->firstWhere('property_name', $name);

        $citation   = $propertyMeta['citation'] ?? null;
        $reason     = $citation['reason']       ?? null;
        $confidence = $citation['confidence']   ?? $attribute['confidence'] ?? null;
        $sources    = $citation['sources']      ?? [];

        $jsonValue = StringHelper::safeJsonDecode($value, maxEntrySize: 100000, forceJson: false);

        $teamObjectAttribute = TeamObjectAttribute::withTrashed()->updateOrCreate([
            'team_object_id' => $teamObject->id,
            'name'           => $name,
        ], [
            'text_value' => $jsonValue ? null : $value,
            'json_value' => $jsonValue ?: null,
            'reason'     => $reason,
            'confidence' => $confidence,
            'deleted_at' => null,
        ]);

        // Clear out the old sources
        if ($sources) {
            $teamObjectAttribute->sources()->delete();
        }
        foreach ($sources as $source) {
            $this->saveTeamObjectAttributeSource($teamObjectAttribute, $source);
        }

        return $teamObjectAttribute;
    }

    /**
     * Create or Update a Team Object Attribute Source record based on team object attribute and source
     */
    public function saveTeamObjectAttributeSource(TeamObjectAttribute $teamObjectAttribute, array $source): TeamObjectAttributeSource
    {
        $sourceUrl       = $source['url']        ?? null;
        $sourceMessageId = $source['message_id'] ?? null;
        $fileId          = $source['file_id']    ?? [];
        $storedFile      = null;

        static::logDebug('Saving citation: ' . $teamObjectAttribute->name . ($sourceUrl ? " URL: $sourceUrl" : '') . ($sourceMessageId ? " AgentThreadMessage ID: $sourceMessageId" : ''));

        if ($sourceUrl) {
            $sourceUrl  = FileHelper::normalizeUrl($sourceUrl);
            $storedFile = StoredFile::firstWhere('url', $sourceUrl);

            if (!$storedFile) {
                static::logDebug('Creating Stored File for source URL');
                $storedFile = app(FileRepository::class)->createFileWithUrl($sourceUrl, $sourceUrl, ['disk' => 'web', 'mime' => FileHelper::getMimeFromExtension($sourceUrl)]);
            }

            static::logDebug("Stored File $storedFile->id references source URL $sourceUrl");
            $sourceId   = $sourceUrl;
            $sourceType = 'file';
        } elseif ($sourceMessageId) {
            $sourceId   = $sourceMessageId;
            $sourceType = 'message';
        } elseif ($fileId) {
            $storedFile = StoredFile::find($fileId);

            if (!$storedFile) {
                throw new Exception("Attribute source requires a valid file_id: Stored File Not Found: $fileId");
            }
            $sourceId   = $fileId;
            $sourceType = 'file';
        } else {
            throw new Exception('Save Team Object Attribute Source requires a File, URL or AgentThreadMessage ID');
        }

        $attributeSource = $teamObjectAttribute->sources()->updateOrCreate([
            'source_type' => $sourceType,
            'source_id'   => $sourceId,
        ], [
            'explanation'             => $source['explanation'] ?? null,
            'agent_thread_message_id' => $sourceMessageId,
            'stored_file_id'          => $storedFile?->id,
        ]);

        static::logDebug("$attributeSource was " . ($attributeSource->wasRecentlyCreated ? 'created' : 'updated'));

        return $attributeSource;
    }

    /**
     * Create or Update a Team Object Relationship record based on team object, relationship name and related object
     */
    public function saveTeamObjectRelationship(TeamObject $teamObject, string $relationshipName, TeamObject $relatedObject): void
    {
        if (!$relationshipName) {
            throw new Exception("Save Objects requires a relationship_name for each relation: TeamObject:\n$teamObject\n\nRelated:\n$relatedObject");
        }

        // Ensure the record is associated
        $relatedObject = TeamObjectRelationship::withTrashed()->firstOrNew([
            'relationship_name'      => $relationshipName,
            'team_object_id'         => $teamObject->id,
            'related_team_object_id' => $relatedObject->id,
        ]);

        if ($relatedObject->deleted_at) {
            $relatedObject->restore();
        }

        $relatedObject->save();
    }

    /**
     * Create or Update multiple Team Object records based on a response schema and objects
     * NOTE: objects are passed as reference so we can update the IDs after creating the objects
     *
     * @return TeamObject[]
     */
    public function saveTeamObjectsUsingSchema(array $schema, array &$objects, ?AgentThreadRun $threadRun = null): array
    {
        static::logDebug('Saving array of TeamObjects: ' . count($objects));

        $teamObjects = [];
        foreach ($objects as &$object) {
            if ($object) {
                $teamObjects[] = $this->saveTeamObjectUsingSchema($schema, $object, $threadRun);
            }
        }

        return $teamObjects;
    }

    /**
     * Create or Update a Team Object record based on a response schema and object.
     *
     * NOTE: object is passed as reference so we can update the ID after creating the object
     */
    public function saveTeamObjectUsingSchema(array $schema, array &$object, ?AgentThreadRun $threadRun = null): TeamObject
    {
        $id           = $object['id']            ?? null;
        $type         = $schema['title']         ?? $object['type'] ?? null;
        $name         = $object['name']['value'] ?? $object['name'] ?? null;
        $propertyMeta = $object['property_meta'] ?? null;

        static::logDebug("Saving TeamObject: $type" . ($id ? "($id)" : '(new)') . " $name");

        // If an ID is set, resolve the existing team object
        if ($id) {
            $teamObject = TeamObject::find($id);

            if (!$teamObject) {
                throw new ValidationError("Failed to save Team Object ($type) $name: Object w/ ID $id not found");
            }

            if (!$this->rootObject) {
                $this->setRootObject($teamObject);
            }

            if ($type && $teamObject->type !== $type) {
                if ($threadRun) {
                    static::logWarning("Team Object ($type: $id): type of object did not match: $type !== $teamObject->type. Setting id to null for LLM agent thread run and continuing...");
                    $object['id'] = null;

                    return $this->saveTeamObjectUsingSchema($schema, $object, $threadRun);
                } else {
                    throw new ValidationError("Failed to save Team Object ($type: $id): type of object did not match: $type !== $teamObject->type");
                }
            }

            // Be sure the type is loaded on the object (for database retrieval purposes from artifacts)
            $object['type'] = $teamObject->type;

            static::logDebug("Loaded for update: $teamObject");
            $this->updateTeamObject($teamObject, $object);
        } else {
            // Perform a create operation that is safe for concurrent requests
            // If this is NOT a root object (ie: parentObject is set), then allow the object to be updated if it already exists
            // NOTE: We do NOT want to allow updating of root objects since this could cause data corruption if the LLM thinks this root object does not exist.
            // We want to make sure we're dealing with the correct root object!
            $teamObject = $this->createTeamObject($type, $name, $object, true);

            $object['id']                   = $teamObject->id;
            $object['type']                 = $teamObject->type;
            $object['was_recently_created'] = $teamObject->wasRecentlyCreated;

            // If there was no root object before, that means this is the root object
            if (!$this->rootObject) {
                $this->setRootObject($teamObject);
            }

            static::logDebug("Creating a new teamObject: $teamObject");
        }

        // Save the properties to the resolved team object
        foreach ($schema['properties'] as $propertyName => $property) {
            $title  = $property['title']  ?? $propertyName;
            $type   = $property['type']   ?? null;
            $format = $property['format'] ?? null;

            if (!$type) {
                throw new Exception("Invalid JSON Schema at: $propertyName");
            }

            // If there is no value set for this property on the object, then skip it
            if (!array_key_exists($propertyName, $object)) {
                continue;
            }

            static::logDebug("Saving Property: $title ($type" . ($format ? " [$format]" : '') . ')');

            // Make sure we keep referencing the object (don't create a new object in memory) so we can continue updating the IDs inline
            if ($object[$propertyName] === null) {
                static::logDebug("Skipping null entry for value of $propertyName");

                continue;
            }

            // Special case where the property is supposed to be an array but has been reduced to an object because there was only 1 array entry
            if ($type === 'array' && array_key_exists('id', $object[$propertyName])) {
                // Flag as an object so we can continue. This is ok!
                $type     = 'object';
                $property = $property['items'];
            }

            if ($type === 'array') {
                // If the property is an array, then save each item in the array as a related object
                // NOTE: The object is still be referenced here and passing to the child as a reference so we can update the ID inline!
                try {
                    $relatedObjects = $this->saveTeamObjectsUsingSchema($property['items'], $object[$propertyName], $threadRun);
                } catch (Throwable $throwable) {
                    static::logDebug("Failed to save array of team objects: $propertyName: " . $throwable->getMessage());

                    continue;
                }

                foreach ($relatedObjects as $relatedObject) {
                    $this->saveTeamObjectRelationship($teamObject, $propertyName, $relatedObject);
                }
            } elseif ($type === 'object') {
                // If the property is an object, then save the object as a related object
                // NOTE: The object is still be referenced here and passing to the child as a reference so we can update the ID inline!
                try {
                    $relatedObject = $this->saveTeamObjectUsingSchema($property, $object[$propertyName], $threadRun);
                } catch (Throwable $throwable) {
                    static::logDebug("Failed to save Team Object: $propertyName: " . $throwable->getMessage());

                    continue;
                }

                $this->saveTeamObjectRelationship($teamObject, $propertyName, $relatedObject);
            } else {
                // NOTE: we are no longer referencing the object! Any changes here will not affect the output object
                $propertyValue = $object[$propertyName];

                // If saving a primitive value type, then convert it to an array with a value key
                if (!is_array($propertyValue) || !array_key_exists('value', $propertyValue)) {
                    $propertyValue = ['value' => $propertyValue];
                }

                // Skip saving this property if the value is null
                if ($propertyValue['value'] === null) {
                    static::logDebug("Skipping null value for $propertyName");

                    continue;
                }

                $propertyValue['value'] = $this->formatPropertyValue($type, $format, $propertyValue['value']);

                // Save the attribute
                $objectAttribute = $this->saveTeamObjectAttribute($teamObject, $propertyName, $propertyValue, $propertyMeta);

                // Associate the thread run if it is set
                if ($objectAttribute && $threadRun) {
                    $objectAttribute->agentThreadRun()->associate($threadRun)->save();
                }
            }
        }

        return $teamObject;
    }

    /**
     * Format a property value based on the type and format
     */
    public function formatPropertyValue($type, $format, $value): string|int|bool|float
    {
        return match ($format || $type) {
            'string'    => (string)$value,
            'number'    => (float)$value,
            'integer'   => (int)$value,
            'boolean'   => (bool)$value,
            'date'      => $this->tryParseDate($value)?->toDateString()     ?? (string)$value,
            'date-time' => $this->tryParseDate($value)?->toDateTimeString() ?? (string)$value,
            default     => $value,
        };
    }

    /**
     * Attempt to parse a date value using Carbon.
     * Returns null if parsing fails (e.g., LLM returned "unknown" or invalid date).
     */
    private function tryParseDate(mixed $value): ?Carbon
    {
        try {
            return carbon($value);
        } catch (Exception) {
            return null;
        }
    }
}
