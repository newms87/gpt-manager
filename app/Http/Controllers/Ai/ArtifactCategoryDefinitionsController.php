<?php

namespace App\Http\Controllers\Ai;

use App\Repositories\ArtifactCategoryDefinitionRepository;
use App\Resources\Schema\ArtifactCategoryDefinitionResource;
use Newms87\Danx\Http\Controllers\ActionController;

class ArtifactCategoryDefinitionsController extends ActionController
{
    public static ?string $repo = ArtifactCategoryDefinitionRepository::class;

    public static ?string $resource = ArtifactCategoryDefinitionResource::class;
}
