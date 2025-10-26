<?php

namespace App\Http\Controllers\Ai;

use App\Repositories\McpServerRepository;
use App\Resources\Agent\McpServerResource;
use Newms87\Danx\Http\Controllers\ActionController;

class McpServersController extends ActionController
{
    public static ?string $repo = McpServerRepository::class;

    public static ?string $resource = McpServerResource::class;
}
