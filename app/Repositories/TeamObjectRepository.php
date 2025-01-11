<?php

namespace App\Repositories;

use App\Models\Agent\ThreadRun;
use App\Models\TeamObject\TeamObject;
use App\Models\TeamObject\TeamObjectAttribute;
use App\Models\TeamObject\TeamObjectAttributeSource;
use App\Models\TeamObject\TeamObjectRelationship;
use App\Resources\TeamObject\TeamObjectAttributeResource;
use BadFunctionCallException;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Log;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Helpers\FileHelper;
use Newms87\Danx\Helpers\ModelHelper;
use Newms87\Danx\Helpers\StringHelper;
use Newms87\Danx\Models\Utilities\StoredFile;
use Newms87\Danx\Repositories\ActionRepository;
use Newms87\Danx\Repositories\FileRepository;

class TeamObjectRepository extends ActionRepository
{
    public static string $model = TeamObject::class;

    public function applyAction(string $action, TeamObject|Model|array|null $model = null, ?array $data = null)
    {
        $type = $data['type'] ?? $model?->type;
        $name = $data['name'] ?? $model?->name;

        return match ($action) {
            'create' => $this->saveTeamObject($type, $name, $data),
            'update' => (bool)$this->updateTeamObject($model, $data),
            'create-relation' => $this->createRelation($model, $data['relationship_name'] ?? null, $type, $name, $data),
            'save-attribute' => TeamObjectAttributeResource::make($this->saveTeamObjectAttribute($model, $data['name'] ?? null, $data)),
            default => parent::applyAction($action, $model, $data)
        };
    }

    /**
     * Create or Update a Team Object record based on type and name
     */
    public function saveTeamObject($type, $name, $input = []): TeamObject
    {
        if (!$type || !$name) {
            throw new BadFunctionCallException("Save Objects requires a type and name for each object: \n\nType: $type\nName: $name\nInput:\n" . json_encode($input));
        }

        $data = [
            'type' => $type,
            'name' => $name,
        ];

        $teamObject = TeamObject::where('type', $type)
            ->where('name', $name)
            ->withTrashed()
            ->first();

        if (!$teamObject) {
            $teamObject = TeamObject::make($data);
        } elseif ($teamObject->deleted_at) {
            $teamObject->restore();
        }

        return $this->updateTeamObject($teamObject, $input);
    }

    /**
     * Update an existing team object
     */
    function updateTeamObject(TeamObject $teamObject, $input = []): TeamObject
    {
        $fillableProps = ['name', 'date', 'description', 'url'];

        // Sometimes a user will choose to explicitly set these object properties as attributes to the object. In this case we want to convert from an object attribute to a string property so we can save it directly on the object.
        // NOTE: The attribute will still be saved as an object attribute related to the object, so this is duplicated information, but easier to access directly on the object, instead of as an object attribute.
        foreach($fillableProps as $propName) {
            if (!empty($input[$propName]) && is_array($input[$propName])) {
                $input[$propName] = $input[$propName]['value'] ?? null;
            }

            if (array_key_exists($propName, $input) && $input[$propName] === null) {
                unset($input[$propName]);
            }
        }

        // Cleanup date inputs in case they are in an unexpected format
        if (!empty($input['date'])) {
            $input['date'] = carbon($input['date']);
        }

        $teamObject->fill($input)->validate()->save();

        return $teamObject;
    }

    /**
     * Create a new Team Object record and a relationship to another Team Object record based on type, name and related
     * object
     */
    public function createRelation(TeamObject $teamObject, $relationshipName, $type, $name, $input = []): TeamObject
    {
        if (!$relationshipName) {
            throw new ValidationError("Save Objects requires a relationship_name for each relation");
        }

        unset($input['name']);

        $name          = ModelHelper::getNextModelName(TeamObject::make(['name' => $name]), 'name', ['type' => $type]);
        $relatedObject = $this->saveTeamObject($type, $name, $input);

        $this->saveTeamObjectRelationship($teamObject, $relationshipName, $relatedObject);

        return $teamObject;
    }

    /**
     * Create or Update the value, date, confidence and sources for a Team Object Attribute record based on team object
     * and property name
     */
    public function saveTeamObjectAttribute(TeamObject $teamObject, $name, $attribute, ?array $meta = []): ?TeamObjectAttribute
    {
        if (!$name) {
            throw new BadFunctionCallException("Save Team Object Attribute requires a name");
        }

        $value = $attribute['value'] ?? null;

        $propertyMeta = collect($meta)->firstWhere('property_name', $name);

        $citation   = $propertyMeta['citation'] ?? null;
        $date       = $citation['date'] ?? null;
        $reason     = $citation['reason'] ?? null;
        $confidence = $citation['confidence'] ?? null;
        $sources    = $citation['sources'] ?? [];

        $jsonValue = StringHelper::safeJsonDecode($value, maxEntrySize: 100000, forceJson: false);

        $teamObjectAttribute = TeamObjectAttribute::updateOrCreate([
            'object_id' => $teamObject->id,
            'name'      => $name,
            'date'      => $date,
        ], [
            'text_value' => $jsonValue ? null : $value,
            'json_value' => $jsonValue ?: null,
            'reason'     => $reason,
            'confidence' => $confidence,
        ]);

        // Clear out the old sources
        if ($sources) {
            $teamObjectAttribute->sources()->delete();
        }
        foreach($sources as $source) {
            $this->saveTeamObjectAttributeSource($teamObjectAttribute, $source);
        }

        return $teamObjectAttribute;
    }

    /**
     * Create or Update a Team Object Attribute Source record based on team object attribute and source
     */
    public function saveTeamObjectAttributeSource(TeamObjectAttribute $teamObjectAttribute, array $source): TeamObjectAttributeSource
    {
        $sourceUrl       = $source['url'] ?? null;
        $sourceMessageId = $source['message_id'] ?? null;
        $storedFile      = null;

        Log::debug("Saving citation: " . $teamObjectAttribute->name . ($sourceUrl ? " URL: $sourceUrl" : '') . ($sourceMessageId ? " Message ID: $sourceMessageId" : ''));

        if ($sourceUrl) {
            $sourceUrl  = FileHelper::normalizeUrl($sourceUrl);
            $storedFile = StoredFile::firstWhere('url', $sourceUrl);

            if (!$storedFile) {
                Log::debug("Creating Stored File for source URL");
                $storedFile = app(FileRepository::class)->createFileWithUrl($sourceUrl, $sourceUrl, ['disk' => 'web', 'mime' => FileHelper::getMimeFromExtension($sourceUrl)]);
            }

            Log::debug("Stored File $storedFile->id references source URL $sourceUrl");
            $sourceId   = $sourceUrl;
            $sourceType = 'file';
        } elseif ($sourceMessageId) {
            $sourceId   = $sourceMessageId;
            $sourceType = 'message';
        } else {
            throw new BadFunctionCallException("Save Team Object Attribute Source requires a URL or Message ID");
        }

        $attributeSource = $teamObjectAttribute->sources()->updateOrCreate([
            'source_type' => $sourceType,
            'source_id'   => $sourceId,
        ], [
            'explanation'    => $source['explanation'] ?? null,
            'message_id'     => $sourceMessageId,
            'stored_file_id' => $storedFile?->id,
        ]);

        Log::debug("$attributeSource was " . ($attributeSource->wasRecentlyCreated ? 'created' : 'updated'));

        return $attributeSource;
    }

    /**
     * Create or Update a Team Object Relationship record based on team object, relationship name and related object
     */
    public function saveTeamObjectRelationship(TeamObject $teamObject, string $relationshipName, TeamObject $relatedObject): void
    {
        if (!$relationshipName) {
            throw new BadFunctionCallException("Save Objects requires a relationship_name for each relation: TeamObject:\n$teamObject\n\nRelated:\n$relatedObject");
        }

        // Ensure the record is associated
        $relatedObject = TeamObjectRelationship::withTrashed()->firstOrNew([
            'relationship_name' => $relationshipName,
            'object_id'         => $teamObject->id,
            'related_object_id' => $relatedObject->id,
        ]);

        if ($relatedObject->deleted_at) {
            $relatedObject->restore();
        }

        $relatedObject->save();
    }

    /**
     * Create or Update multiple Team Object records based on a response schema and objects
     * @return TeamObject[]
     */
    public function saveTeamObjectsUsingSchema(array $schema, array $objects, ThreadRun $threadRun = null): array
    {
        Log::debug("Saving array of TeamObjects: " . count($objects));

        $teamObjects = [];
        foreach($objects as $object) {
            $teamObjects[] = $this->saveTeamObjectUsingSchema($schema, $object, $threadRun);
        }

        return $teamObjects;
    }

    /**
     * Create or Update a Team Object record based on a response schema and object
     */
    public function saveTeamObjectUsingSchema(array $schema, array $object, ThreadRun $threadRun = null): TeamObject
    {
        $type         = $schema['title'] ?? null;
        $name         = $object['name']['value'] ?? $object['name'] ?? null;
        $propertyMeta = $object['property_meta'] ?? null;

        Log::debug("Saving TeamObject: $type $name");

        $teamObject = $this->saveTeamObject($type, $name, $object);

        foreach($schema['properties'] as $propertyName => $property) {
            $title  = $property['title'] ?? $propertyName;
            $type   = $property['type'] ?? null;
            $format = $property['format'] ?? null;

            if (!$type) {
                throw new Exception("Invalid JSON Schema at: $propertyName");
            }

            // If there is no value set for this property on the object, then skip it
            if (!array_key_exists($propertyName, $object)) {
                continue;
            }

            Log::debug("Saving Property: $title ($type" . ($format ? " [$format]" : '') . ')');

            $propertyValue = $object[$propertyName];

            if ($propertyValue === null) {
                Log::debug("Skipping null entry for value of $propertyName");
                continue;
            }

            if ($type === 'array') {
                // If the property is an array, then save each item in the array as a related object
                $relatedObjects = $this->saveTeamObjectsUsingSchema($property['items'], $propertyValue, $threadRun);
                foreach($relatedObjects as $relatedObject) {
                    $this->saveTeamObjectRelationship($teamObject, $propertyName, $relatedObject);
                }
            } elseif ($type === 'object') {
                // If the property is an object, then save the object as a related object
                $relatedObject = $this->saveTeamObjectUsingSchema($property, $propertyValue, $threadRun);
                $this->saveTeamObjectRelationship($teamObject, $propertyName, $relatedObject);
            } else {
                if (!$propertyMeta || !array_filter($propertyMeta, fn($meta) => $meta['property_name'] === $propertyName)) {
                    Log::debug("Property meta was null: Skipping save to DB for $propertyName");
                    continue;
                }

                // If saving a primitive value type, then convert it to an array with a value key
                if (!is_array($propertyValue) || !array_key_exists('value', $propertyValue)) {
                    $propertyValue = ['value' => $propertyValue];
                }

                // Skip saving this property if the value is null
                if ($propertyValue['value'] === null) {
                    Log::debug("Skipping null value for $propertyName");
                    continue;
                }

                $propertyValue['value'] = $this->formatPropertyValue($type, $format, $propertyValue['value']);

                // Save the attribute
                $objectAttribute = $this->saveTeamObjectAttribute($teamObject, $propertyName, $propertyValue, $propertyMeta);

                // Associate the thread run if it is set
                if ($objectAttribute && $threadRun) {
                    $objectAttribute->threadRun()->associate($threadRun)->save();
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
            'string' => (string)$value,
            'number' => (float)$value,
            'integer' => (int)$value,
            'boolean' => (bool)$value,
            'date' => carbon($value)->toDateString(),
            'date-time' => carbon($value)->toDateTimeString(),
            default => $value,
        };
    }

    /**
     * Load a Team Object record based on type and ID
     */
    public function loadTeamObject($type, $id): ?TeamObject
    {
        return TeamObject::where('id', $id)->where('type', $type)->first();
    }
}
