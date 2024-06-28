<?php

namespace App\Http\Controllers\Ai;

use App\Repositories\ThreadRepository;
use App\Resources\Agent\ThreadResource;
use Newms87\Danx\Http\Controllers\ActionController;

class ThreadsController extends ActionController
{
    public static string  $repo     = ThreadRepository::class;
    public static ?string $resource = ThreadResource::class;
}
