<?php

namespace App\AiTools\SaveTeamObjects;

use App\AiTools\AiToolAbstract;
use App\AiTools\AiToolContract;
use App\AiTools\AiToolResponse;
use App\Models\TeamObject\TeamObject;
use App\Repositories\TeamObjectRepository;
use BadFunctionCallException;
use Illuminate\Support\Facades\Log;

class SaveTeamObjectsAiTool extends AiToolAbstract implements AiToolContract
{
    public static string $name = 'save-team-objects';

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

        $teamObject = app(TeamObjectRepository::class)->saveTeamObject($type, $name, $object);

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

        if (!$relatedType) {
            throw new BadFunctionCallException("Save Objects requires a type for each relation: \n\n" . json_encode($relation));
        }

        $teamRepo = app(TeamObjectRepository::class);

        if ($relatedId) {
            $relatedObject = TeamObject::where('type', $relatedType)->find($relatedId);

            if (!$relatedObject) {
                throw new BadFunctionCallException("Could not find related object: $relatedType ($relatedId)");
            }
            $relation['name'] = $relatedObject->name;
        }

        // Recursively save the related object
        $relatedObject = $this->saveObject($relation);

        if (!$relatedObject) {
            throw new BadFunctionCallException("Could not resolve related object: \n\n" . json_encode($relation));
        }

        $teamRepo->saveTeamObjectRelationship($teamObject, $relationshipName, $relatedObject);
    }

    public function saveObjectAttribute($object, $attribute): void
    {
        $name       = $attribute['name'] ?? null;
        $messageIds = $attribute['message_ids'] ?? [];

        if (!array_key_exists('value', $attribute)) {
            throw new BadFunctionCallException("Save Objects requires a value for each attribute: \n\n" . json_encode($attribute));
        }

        app(TeamObjectRepository::class)->saveTeamObjectAttribute($object, $name, $attribute, $messageIds, $this->threadRun);
    }
}
