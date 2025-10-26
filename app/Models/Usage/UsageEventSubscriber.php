<?php

namespace App\Models\Usage;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class UsageEventSubscriber extends Model
{
    protected $table    = 'usage_event_subscribers';

    protected $fillable = ['usage_event_id', 'subscriber_type', 'subscriber_id', 'subscribed_at'];

    public function subscriber(): MorphTo
    {
        return $this->morphTo('subscriber');
    }
}
