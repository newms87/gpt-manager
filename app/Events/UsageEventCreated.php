<?php

namespace App\Events;

use App\Models\Usage\UsageEvent;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UsageEventCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public UsageEvent $usageEvent)
    {
    }
}
