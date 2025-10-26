<?php

namespace App\Http\Controllers\Ai;

use App\Repositories\SchemaFragmentRepository;
use App\Resources\Schema\SchemaFragmentResource;
use Newms87\Danx\Http\Controllers\ActionController;

class SchemaFragmentsController extends ActionController
{
    public static ?string $repo     = SchemaFragmentRepository::class;

    public static ?string $resource = SchemaFragmentResource::class;
}
