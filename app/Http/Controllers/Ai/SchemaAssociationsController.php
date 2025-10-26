<?php

namespace App\Http\Controllers\Ai;

use App\Repositories\SchemaAssociationRepository;
use App\Resources\Schema\SchemaAssociationResource;
use Newms87\Danx\Http\Controllers\ActionController;

class SchemaAssociationsController extends ActionController
{
    public static ?string $repo     = SchemaAssociationRepository::class;

    public static ?string $resource = SchemaAssociationResource::class;
}
