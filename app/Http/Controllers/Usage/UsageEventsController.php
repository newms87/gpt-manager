<?php

namespace App\Http\Controllers\Usage;

use App\Repositories\UsageEventRepository;
use App\Resources\Usage\UsageEventResource;
use Newms87\Danx\Http\Controllers\ActionController;

class UsageEventsController extends ActionController
{
    public static ?string $repo     = UsageEventRepository::class;
    public static ?string $resource = UsageEventResource::class;
}
