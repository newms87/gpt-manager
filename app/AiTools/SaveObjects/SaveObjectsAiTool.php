<?php

namespace App\AiTools\SaveObjects;

use App\AiTools\AiToolAbstract;
use App\AiTools\AiToolContract;
use App\AiTools\AiToolResponse;
use App\Models\TeamObject\TeamObject;
use App\Models\TeamObject\TeamObjectAttribute;
use App\Models\TeamObject\TeamObjectRelationship;
use App\Services\Database\SchemaManager;
use BadFunctionCallException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Newms87\Danx\Helpers\FileHelper;
use Newms87\Danx\Models\Utilities\StoredFile;
use Newms87\Danx\Repositories\FileRepository;
use Str;

class SaveObjectsAiTool extends AiToolAbstract implements AiToolContract
{
    public static string $name = 'save-objects';

    protected SchemaManager $schemaManager;

    public function __construct()
    {
        $schemaFile          = database_path('schemas/object_relationships.yaml');
        $this->schemaManager = new SchemaManager(team()->namespace, $schemaFile);
    }

    public function execute($params): AiToolResponse
    {
        $objects = $params['objects'] ?? null;

        Log::debug("Executing Save Objects AI Tool: " . count($objects) . ' objects');

        if (!$objects || !is_array($objects)) {
            throw new BadFunctionCallException("Save Objects requires an array of objects to save: \n\n" . json_encode($objects));
        }

        $response = new AiToolResponse();


        foreach($objects as $object) {
            $savedObject = $this->saveObject($object);

            $response->addContent("Saved $savedObject->type ($savedObject->id): $savedObject->name");
        }

        return $response;
    }

    public function saveObject($object): object
    {
        $type       = $object['type'] ?? null;
        $name       = $object['name'] ?? null;
        $relations  = $object['relations'] ?? [];
        $attributes = $object['attributes'] ?? [];

        if (!$type || !$name) {
            throw new BadFunctionCallException("Save Objects requires a type and name for each object: \n\n" . json_encode($object));
        }

        $data = [
            'type' => $type,
            'ref'  => Str::slug($name),
            'name' => $name,
        ];

        // If the keys are set for additional fields, update the fields with those values (including null)
        foreach(['description', 'url', 'meta'] as $key) {
            if (array_key_exists($key, $object)) {
                $data[$key] = $object[$key];
            }
        }

        $teamObject = TeamObject::where('type', $type)->where(fn(Builder $builder) => $builder->where('name', $name)->orWhere('ref', $data['ref']))->first();

        if ($teamObject) {
            $teamObject->update($data);
        } else {
            $teamObject = TeamObject::create($data);
        }

        foreach($relations as $relation) {
            $this->saveObjectRelation($teamObject, $relation);
        }

        foreach($attributes as $attribute) {
            $this->saveObjectAttribute($teamObject, $attribute);
        }

        return $teamObject;
    }

    public function saveObjectRelation(TeamObject $teamObject, $relation): void
    {
        $relationshipName = $relation['relationship_name'] ?? null;
        $relatedId        = $relation['related_id'] ?? $relation['related_ref'] ?? null;
        $relatedType      = $relation['type'] ?? null;
        $relatedName      = $relation['name'] ?? null;

        if (!$relationshipName) {
            throw new BadFunctionCallException("Save Objects requires a relationship_name for each relation: \n\n" . json_encode($relation));
        }

        if (!$relatedType) {
            throw new BadFunctionCallException("Save Objects requires a type for each relation: \n\n" . json_encode($relation));
        }

        if (!$relatedId && !$relatedName) {
            throw new BadFunctionCallException("Save Objects requires a related_id or name for each relation: \n\n" . json_encode($relation));
        }

        if ($relatedId) {
            $relatedObject = TeamObject::where('type', $relatedType)->find($relatedId);
        } else {
            $relatedObject = $this->saveObject($relation);
        }

        if (!$relatedObject) {
            throw new BadFunctionCallException("Could not resolve related object: \n\n" . json_encode($relation));
        }

        // Ensure the record is associated
        TeamObjectRelationship::updateOrCreate([
            'relationship_name' => $relationshipName,
            'object_id'         => $teamObject->id,
            'related_object_id' => $relatedObject->id,
        ]);
    }

    public function saveObjectAttribute($object, $attribute): void
    {
        $name       = $attribute['name'] ?? null;
        $value      = $attribute['value'] ?? null;
        $date       = $attribute['date'] ?? null;
        $sourceUrl  = $attribute['source_url'] ?? null;
        $messageIds = $attribute['message_ids'] ?? [];

        if (!$name || (!$value && !array_key_exists('value', $attribute))) {
            throw new BadFunctionCallException("Save Objects requires a name and value for each attribute: \n\n" . json_encode($attribute));
        }

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

        $teamObjectAttribute = TeamObjectAttribute::updateOrCreate([
            'object_id' => $object->id,
            'name'      => $name,
            'date'      => $date,
        ], [
            'text_value'            => is_array($value) ? null : $value,
            'json_value'            => is_array($value) ? json_encode($value) : null,
            'source_stored_file_id' => $storedFile?->id,
        ]);

        if ($messageIds) {
            $teamObjectAttribute->sourceMessages()->syncWithoutDetaching($messageIds);
        }
    }
}
