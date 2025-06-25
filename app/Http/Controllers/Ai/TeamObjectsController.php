<?php

namespace App\Http\Controllers\Ai;

use App\Models\TeamObject\TeamObject;
use App\Repositories\TeamObjectRepository;
use App\Resources\TeamObject\TeamObjectResource;
use App\Services\TeamObject\TeamObjectMergeService;
use Newms87\Danx\Http\Controllers\ActionController;

class TeamObjectsController extends ActionController
{
    public static ?string $repo     = TeamObjectRepository::class;
    public static ?string $resource = TeamObjectResource::class;

    public function merge(TeamObject $sourceObject, TeamObject $targetObject)
    {
        $mergedObject = app(TeamObjectMergeService::class)->merge($sourceObject, $targetObject);

        return new TeamObjectResource($mergedObject);
    }
}
