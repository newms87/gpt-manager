<?php

namespace App\Repositories;

use App\Models\Usage\UsageEvent;
use Newms87\Danx\Repositories\ActionRepository;

class UsageEventRepository extends ActionRepository
{
    public static string $model = UsageEvent::class;
}
