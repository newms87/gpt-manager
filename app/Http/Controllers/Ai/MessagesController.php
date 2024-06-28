<?php

namespace App\Http\Controllers\Ai;

use App\Repositories\MessageRepository;
use App\Resources\Agent\MessageResource;
use Newms87\Danx\Http\Controllers\ActionController;

class MessagesController extends ActionController
{
    public static string  $repo     = MessageRepository::class;
    public static ?string $resource = MessageResource::class;
}
