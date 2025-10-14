<?php

namespace App\Events;

use App\Models\TeamObject\TeamObject;
use Illuminate\Broadcasting\PrivateChannel;
use Newms87\Danx\Events\ModelSavedEvent;

class TeamObjectUpdatedEvent extends ModelSavedEvent
{
    public function __construct(protected TeamObject $teamObject, protected string $event)
    {
        parent::__construct($teamObject, $event);
    }

    public function getTeamObject(): TeamObject
    {
        return $this->teamObject;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('TeamObject.' . $this->teamObject->team_id);
    }

    public function data(): array
    {
        return [
            'id'                   => $this->teamObject->id,
            'root_object_id'       => $this->teamObject->root_object_id ?? $this->teamObject->id,
            'schema_definition_id' => $this->teamObject->schema_definition_id,
            'updated_at'           => $this->teamObject->updated_at,
            '__type'               => 'TeamObjectEvent',
        ];
    }
}
