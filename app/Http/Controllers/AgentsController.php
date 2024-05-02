<?php

namespace App\Http\Controllers;

use App\Repositories\AgentRepository;
use App\Resources\AgentResource;
use Flytedan\DanxLaravel\Http\Controllers\ActionController;

class AgentsController extends ActionController
{
    public static string  $repo            = AgentRepository::class;
    public static ?string $resource        = AgentResource::class;
    public static ?string $detailsResource = AgentResource::class;
}
