<?php

namespace App\Models\Agent;

use App\Models\Team\Team;
use App\Models\Workflow\WorkflowJob;
use App\Repositories\AgentRepository;
use Exception;
use Flytedan\DanxLaravel\Contracts\AuditableContract;
use Flytedan\DanxLaravel\Traits\AuditableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Agent extends Model implements AuditableContract
{
    use HasFactory, AuditableTrait, SoftDeletes;

    protected $fillable = [
        'name',
        'model',
        'temperature',
        'prompt',
        'description',
    ];

    public function casts()
    {
        return [
            'functions' => 'json',
        ];
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function knowledge()
    {
        return $this->belongsTo(Knowledge::class);
    }

    public function threads()
    {
        return $this->hasMany(Thread::class);
    }

    public function workflowJobs()
    {
        return $this->belongsToMany(WorkflowJob::class);
    }

    public function getModelApi()
    {
        $apiClass = config('ai.apis')[$this->api] ?? null;
        if (!$apiClass) {
            throw new Exception('API class not found for ' . $this->api);
        }

        return new $apiClass();
    }

    public static function booted()
    {
        static::creating(function (Agent $agent) {
            $agent->team_id = $agent->team_id ?? user()->team_id;
        });

        static::saving(function (Agent $agent) {
            if ($agent->isDirty('model')) {
                $agent->api = AgentRepository::getApiFromModel($agent->model);
            }
        });
    }
}
