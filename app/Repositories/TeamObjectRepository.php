<?php

namespace App\Repositories;

use App\Models\TeamObject\TeamObject;
use App\Models\TeamObject\TeamObjectAttribute;
use App\Models\TeamObject\TeamObjectRelationship;
use Log;
use Newms87\Danx\Resources\StoredFileResource;
use Str;

class TeamObjectRepository
{
    public function loadTeamObject($type, $id): ?TeamObject
    {
        return TeamObject::where('id', $id)->where('type', $type)->first();
    }

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

    public function loadTeamObjectAttributes(TeamObject $teamObject): void
    {
        $attributes = TeamObjectAttribute::where('object_id', $teamObject->id)->get();

        foreach($attributes as $attribute) {
            $currentValue = $teamObject->getAttribute($attribute->name);
            if (!$currentValue) {
                $currentValue = [
                    'id'         => $attribute->id,
                    'name'       => $attribute->name,
                    'date'       => $attribute->date,
                    'value'      => $attribute->getValue(),
                    'source'     => StoredFileResource::make($attribute->sourceFile),
                    'dates'      => [],
                    'created_at' => $attribute->created_at,
                    'updated_at' => $attribute->updated_at,
                ];
            } elseif (!$attribute->date) {
                // If date is not set, this is the primary attribute (overwrite it)
                $currentValue['id']         = $attribute->id;
                $currentValue['date']       = null;
                $currentValue['value']      = $attribute->getValue();
                $currentValue['source']     = StoredFileResource::make($attribute->sourceFile);
                $currentValue['created_at'] = $attribute->created_at;
                $currentValue['updated_at'] = $attribute->updated_at;
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
