<?php

namespace App\Http\Controllers\Ai;

use App\Models\Agent\Thread;
use App\Repositories\ThreadRepository;
use App\Resources\ThreadResource;
use App\Resources\ThreadRunResource;
use Flytedan\DanxLaravel\Http\Controllers\ActionController;

class ThreadsController extends ActionController
{
    public static string  $repo            = ThreadRepository::class;
    public static ?string $resource        = ThreadResource::class;
    public static ?string $detailsResource = ThreadResource::class;

    public function run(Thread $thread)
    {
        $threadRun = $this->repo()->run($thread);

        return [
            'success' => true,
            'item'    => ThreadResource::make($thread->refresh()),
            'result'  => ThreadRunResource::make($threadRun),
        ];
    }
}
