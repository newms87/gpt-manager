<?php

namespace App\Http\Controllers\Ai;

use App\Repositories\ArtifactRepository;
use App\Resources\Workflow\ArtifactResource;
use Newms87\Danx\Http\Controllers\ActionController;

class ArtifactsController extends ActionController
{
    public static ?string $repo     = ArtifactRepository::class;
    public static ?string $resource = ArtifactResource::class;
}
