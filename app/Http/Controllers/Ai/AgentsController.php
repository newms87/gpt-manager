<?php

namespace App\Http\Controllers\Ai;

use App\Repositories\AgentRepository;
use App\Resources\Agent\AgentResource;
use Newms87\Danx\Http\Controllers\ActionController;

class AgentsController extends ActionController
{
    public static string  $repo     = AgentRepository::class;
    public static ?string $resource = AgentResource::class;
}
