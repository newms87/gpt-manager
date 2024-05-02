<?php

namespace App\Http\Controllers\Ai;

use App\Repositories\ThreadRepository;
use App\Resources\ThreadResource;
use Flytedan\DanxLaravel\Http\Controllers\ActionController;

class ThreadsController extends ActionController
{
    public static string  $repo            = ThreadRepository::class;
    public static ?string $resource        = ThreadResource::class;
    public static ?string $detailsResource = ThreadResource::class;
}
