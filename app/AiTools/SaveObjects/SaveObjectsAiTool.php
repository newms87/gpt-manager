<?php

namespace App\AiTools\SaveObjects;

use App\AiTools\AiToolAbstract;
use App\AiTools\AiToolContract;
use App\AiTools\AiToolResponse;
use App\Services\Database\SchemaManager;
use BadFunctionCallException;
use Illuminate\Support\Facades\Log;
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

        $storedObject = $this->schemaManager->query('objects')->createOrUpdateWithRef($data);

        foreach($relations as $relation) {
            $this->saveObjectRelation($storedObject, $relation);
        }

        foreach($attributes as $attribute) {
            $this->saveObjectAttribute($storedObject, $attribute);
        }

        return $storedObject;
    }

    public function saveObjectRelation(object $object, $relation): void
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
            $relatedObject = $this->schemaManager->query('objects')->where('type', $relatedType)->find($relatedId);
        } else {
            $relatedObject = $this->saveObject($relation);
        }

        if (!$relatedObject) {
            throw new BadFunctionCallException("Could not resolve related object: \n\n" . json_encode($relation));
        }

        // Ensure the record is associated
        $this->schemaManager->query('object_relationships')->createOrUpdate([
            'relationship_name' => $relationshipName,
            'object_id'         => $object->id,
            'related_object_id' => $relatedObject->id,
        ]);
    }

    public function saveObjectAttribute($object, $attribute): void
    {
        $name      = $attribute['name'] ?? null;
        $value     = $attribute['value'] ?? null;
        $date      = $attribute['date'] ?? null;
        $sourceUrl = $attribute['source_url'] ?? null;

        if (!$name || !$value) {
            throw new BadFunctionCallException("Save Objects requires a name and value for each attribute: \n\n" . json_encode($attribute));
        }

        // TODO: Add source URL and message to the attribute

        $this->schemaManager->query('object_attributes')->createOrUpdate([
            'object_id' => $object->id,
            'name'      => $name,
        ], [
            'date'       => $date,
            'text_value' => is_array($value) ? null : $value,
            'json_value' => is_array($value) ? json_encode($value) : null,
        ]);
    }
}
