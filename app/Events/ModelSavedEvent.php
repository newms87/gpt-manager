<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

abstract class ModelSavedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(protected Model $model, protected string $event)
    {
    }

    public static function lockKey(Model $model): string
    {
        return $model->getTable() . '.' . $model->getKey();
    }

    public static function dispatch(Model $model): void
    {
        $lock = Cache::lock(static::lockKey($model), 5);

        if ($lock->get()) {
            $event = 'deleted';
            if ($model->wasRecentlyCreated) {
                $event = 'created';
            } elseif ($model->exists) {
                $event = 'updated';
            }
            event(new static($model, $event));
        }
    }

    abstract public function broadcastOn();

    public function broadcastAs()
    {
        return $this->event;
    }

    abstract public function data(): array;

    public function broadcastWith()
    {
        $data = $this->data();
        Cache::lock(static::lockKey($this->model))->forceRelease();

        return $data;
    }
}
