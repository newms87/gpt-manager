<?php

namespace App\Http\Controllers\Ai;

use App\Repositories\MessageRepository;
use App\Resources\MessageResource;
use Flytedan\DanxLaravel\Http\Controllers\ActionController;

class MessagesController extends ActionController
{
    public static string  $repo            = MessageRepository::class;
    public static ?string $resource        = MessageResource::class;
    public static ?string $detailsResource = MessageResource::class;
}
