<?php

namespace App\Http\Controllers\Ai;

use App\Repositories\InputSourcesRepository;
use App\Resources\InputSource\InputSourceDetailsResource;
use App\Resources\InputSource\InputSourceResource;
use Newms87\Danx\Http\Controllers\ActionController;

class InputSourcesController extends ActionController
{
    public static string  $repo            = InputSourcesRepository::class;
    public static ?string $resource        = InputSourceResource::class;
    public static ?string $detailsResource = InputSourceDetailsResource::class;
}
