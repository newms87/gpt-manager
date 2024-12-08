<?php

namespace App\Repositories;

use App\Models\Agent\ThreadRun;
use App\Models\TeamObject\TeamObject;
use App\Models\TeamObject\TeamObjectAttribute;
use App\Models\TeamObject\TeamObjectRelationship;
use App\Resources\Agent\MessageResource;
use App\Resources\TeamObject\TeamObjectAttributeResource;
use BadFunctionCallException;
use Illuminate\Database\Eloquent\Model;
use Log;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Helpers\FileHelper;
use Newms87\Danx\Helpers\ModelHelper;
use Newms87\Danx\Helpers\StringHelper;
use Newms87\Danx\Models\Utilities\StoredFile;
use Newms87\Danx\Repositories\ActionRepository;
use Newms87\Danx\Repositories\FileRepository;
use Newms87\Danx\Resources\StoredFileResource;
use Str;

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
     * and attribute name
     */
    public function saveTeamObjectAttribute(
        TeamObject $teamObject,
                   $name,
                   $attribute,
                   $messageIds = [],
        ThreadRun  $threadRun = null
    ): TeamObjectAttribute
    {
        if (!$name) {
            throw new BadFunctionCallException("Save Team Object Attribute requires a name");
        }

        $value       = $attribute['value'] ?? null;
        $date        = $attribute['date'] ?? null;
        $description = $attribute['description'] ?? null;
        $confidence  = $attribute['confidence'] ?? null;
        $sourceUrl   = $attribute['source_url'] ?? null;

        $storedFile = null;

        if ($sourceUrl) {
            $sourceUrl  = FileHelper::normalizeUrl($sourceUrl);
            $storedFile = StoredFile::firstWhere('url', $sourceUrl);

            if (!$storedFile) {
                Log::debug("Creating Stored File for source URL");
                $storedFile = app(FileRepository::class)->createFileWithUrl($sourceUrl, $sourceUrl, ['disk' => 'web', 'mime' => StoredFile::MIME_HTML]);
            }

            Log::debug("Stored File $storedFile->id references source URL $sourceUrl");
        }

        $jsonValue = StringHelper::safeJsonDecode($value, maxEntrySize: 100000, forceJson: false);

        $teamObjectAttribute = TeamObjectAttribute::updateOrCreate([
            'object_id' => $teamObject->id,
            'name'      => $name,
            'date'      => $date,
        ], [
            'text_value'            => $jsonValue ? null : $value,
            'json_value'            => $jsonValue ?: null,
            'description'           => $description,
            'confidence'            => $confidence,
            'source_stored_file_id' => $storedFile?->id,
            'thread_run_id'         => $threadRun?->id,
        ]);

        if ($messageIds) {
            // Filter out empty / null values from message ids
            $messageIds = array_filter($messageIds);
            $teamObjectAttribute->sourceMessages()->syncWithoutDetaching($messageIds);
        }

        return $teamObjectAttribute;
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
     * Load a Team Object record based on type and ID
     */
    public function loadTeamObject($type, $id): ?TeamObject
    {
        return TeamObject::where('id', $id)->where('type', $type)->first();
    }

    /**
     * Load a Team Object record based on type and name and load all attributes and relationships (recursively)
     */
    public function getFullyLoadedTeamObject($type, $id): ?TeamObject
    {
        $object = $this->loadTeamObject($type, $id);

        if (!$object) {
            return null;
        }

        $this->loadTeamObjectAttributes($object);
        $this->recursivelyLoadTeamObjectRelations($object);

        return $object;
    }

    /**
     * Load all attributes for a Team Object record
     */
    public function loadTeamObjectAttributes(TeamObject $teamObject): void
    {
        $attributes = TeamObjectAttribute::where('object_id', $teamObject->id)->with('sourceFile', 'sourceMessages')->get();

        foreach($attributes as $attribute) {
            $currentValue = $teamObject->getAttribute($attribute->name);
            if (!$currentValue) {
                $currentValue = [
                    'id'             => $attribute->id,
                    'name'           => $attribute->name,
                    'date'           => $attribute->date,
                    'value'          => $attribute->getValue(),
                    'source'         => StoredFileResource::make($attribute->sourceFile),
                    'sourceMessages' => MessageResource::collection($attribute->sourceMessages),
                    'dates'          => [],
                    'created_at'     => $attribute->created_at,
                    'updated_at'     => $attribute->updated_at,
                ];
            } elseif (!$attribute->date) {
                // If date is not set, this is the primary attribute (overwrite it)
                $currentValue['id']             = $attribute->id;
                $currentValue['date']           = null;
                $currentValue['value']          = $attribute->getValue();
                $currentValue['source']         = StoredFileResource::make($attribute->sourceFile);
                $currentValue['sourceMessages'] = MessageResource::collection($attribute->sourceMessages);
                $currentValue['created_at']     = $attribute->created_at;
                $currentValue['updated_at']     = $attribute->updated_at;
            }

            if ($attribute->date) {
                $currentValue['dates'][] = [
                    'date'  => $attribute->date,
                    'value' => $attribute->getValue(),
                ];
            }

            $teamObject->setAttribute($attribute->name, $currentValue);
        }
    }

    /**
     * Load all relationships for a Team Object record and recursively load all attributes and relationships
     */
    protected function recursivelyLoadTeamObjectRelations(TeamObject $teamObject, $maxDepth = 10): void
    {
        $relationships = TeamObjectRelationship::where('object_id', $teamObject->id)->get();

        foreach($relationships as $relationship) {
            $object = TeamObject::find($relationship->related_object_id);

            if (!$object) {
                Log::warning("Could not find related object with ID: $relationship->related_object_id");
                continue;
            }

            $this->loadTeamObjectAttributes($object);

            // Otherwise set the object as the relationship
            $currentRelation = $object;

            // If the relationship is plural, add the object to the relationship array
            if (Str::plural($relationship->relationship_name) === $relationship->relationship_name) {
                if ($teamObject->relationLoaded($relationship->relationship_name)) {
                    $currentRelation = $teamObject->getRelation($relationship->relationship_name);
                    $currentRelation->push($object);
                } else {
                    $currentRelation = collect([$object]);
                }
            }

            $teamObject->setRelation($relationship->relationship_name, $currentRelation);

            // Keep loading recursively if we haven't reached the max depth
            if ($maxDepth > 0) {
                $this->recursivelyLoadTeamObjectRelations($object, $maxDepth - 1);
            } else {
                Log::warning("Max depth reached for object with ID: $teamObject->id");
            }
        }
    }
}
