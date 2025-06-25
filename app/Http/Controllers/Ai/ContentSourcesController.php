<?php

namespace App\Http\Controllers\Ai;

use App\Repositories\ContentSourceRepository;
use App\Resources\ContentSource\ContentSourceResource;
use Newms87\Danx\Http\Controllers\ActionController;

class ContentSourcesController extends ActionController
{
    public static ?string $repo     = ContentSourceRepository::class;
    public static ?string $resource = ContentSourceResource::class;
}
