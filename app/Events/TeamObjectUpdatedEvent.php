<?php

namespace App\Events;

use App\Models\TeamObject\TeamObject;
use App\Resources\TeamObject\TeamObjectResource;
use Newms87\Danx\Events\ModelSavedEvent;

class TeamObjectUpdatedEvent extends ModelSavedEvent
{
    public function __construct(protected TeamObject $teamObject, protected string $event)
    {
        parent::__construct(
            $teamObject,
            $event,
            TeamObjectResource::class,
            $teamObject->team_id
        );
    }

    protected function createdData(): array
    {
        return TeamObjectResource::make($this->teamObject, [
            '*'                    => false,
            'type'                 => true,
            'name'                 => true,
            'root_object_id'       => true,
            'schema_definition_id' => true,
            'created_at'           => true,
        ]);
    }

    protected function updatedData(): array
    {
        return TeamObjectResource::make($this->teamObject, [
            '*'                    => false,
            'root_object_id'       => true,
            'schema_definition_id' => true,
            'updated_at'           => true,
        ]);
    }
}
