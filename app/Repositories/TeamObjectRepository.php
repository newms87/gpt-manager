<?php

namespace App\Repositories;

use App\Models\TeamObject\TeamObject;
use App\Models\TeamObject\TeamObjectAttribute;
use App\Models\TeamObject\TeamObjectRelationship;
use App\Resources\Agent\MessageResource;
use BadFunctionCallException;
use Illuminate\Database\Eloquent\Builder;
use Log;
use Newms87\Danx\Helpers\FileHelper;
use Newms87\Danx\Models\Utilities\StoredFile;
use Newms87\Danx\Repositories\FileRepository;
use Newms87\Danx\Resources\StoredFileResource;
use Str;

class TeamObjectRepository
{
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
            'ref'  => Str::slug($name),
            'name' => $name,
        ];

        // If the keys are set for additional fields, update the fields with those values (including null)
        foreach(['description', 'url', 'meta'] as $key) {
            if (array_key_exists($key, $input)) {
                $data[$key] = $input[$key];
            }
        }

        $teamObject = TeamObject::where('type', $type)
            ->where(fn(Builder $builder) => $builder->where('name', $name)->orWhere('ref', $data['ref']))
            ->first();

        if ($teamObject) {
            $teamObject->update($data);
        } else {
            $teamObject = TeamObject::create($data);
        }

        return $teamObject;
    }

    /**
     * Create or Update the value, date, confidence and sources for a Team Object Attribute record based on team object
     * and attribute name
     */
    public function saveTeamObjectAttribute(TeamObject $teamObject, $name, $value, $date = null, $confidence = null, $sourceUrl = null, $messageIds = []): TeamObjectAttribute
    {
        if (!$name) {
            throw new BadFunctionCallException("Save Team Object Attribute requires a name");
        }

        $storedFile = null;

        if ($sourceUrl) {
            $sourceUrl  = FileHelper::normalizeUrl($sourceUrl);
            $storedFile = StoredFile::firstWhere('url', $sourceUrl);

            if (!$storedFile) {
                \Illuminate\Support\Facades\Log::debug("Creating Stored File for source URL");
                $storedFile = app(FileRepository::class)->createFileWithUrl($sourceUrl, $sourceUrl, ['disk' => 'web', 'mime' => StoredFile::MIME_HTML]);
            }

            Log::debug("Stored File $storedFile->id references source URL $sourceUrl");
        }

        $teamObjectAttribute = TeamObjectAttribute::updateOrCreate([
            'object_id' => $teamObject->id,
            'name'      => $name,
            'date'      => $date,
        ], [
            'text_value'            => is_array($value) ? null : $value,
            'json_value'            => is_array($value) ? json_encode($value) : null,
            'confidence'            => $confidence,
            'source_stored_file_id' => $storedFile?->id,
        ]);

        if ($messageIds) {
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
        TeamObjectRelationship::updateOrCreate([
            'relationship_name' => $relationshipName,
            'object_id'         => $teamObject->id,
            'related_object_id' => $relatedObject->id,
        ]);
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
