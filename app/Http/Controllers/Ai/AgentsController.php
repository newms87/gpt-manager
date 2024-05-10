<?php

namespace App\Http\Controllers\Ai;

use App\Repositories\AgentRepository;
use App\Resources\Agent\AgentDetailsResource;
use App\Resources\Agent\AgentResource;
use Flytedan\DanxLaravel\Http\Controllers\ActionController;

class AgentsController extends ActionController
{
    public static string  $repo            = AgentRepository::class;
    public static ?string $resource        = AgentResource::class;
    public static ?string $detailsResource = AgentDetailsResource::class;
}
