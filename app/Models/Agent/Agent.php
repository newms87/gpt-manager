<?php

namespace App\Models\Agent;

use App\Api\AgentApiContracts\AgentApiContract;
use App\Models\Team\Team;
use App\Models\Workflow\WorkflowJob;
use Exception;
use Flytedan\DanxLaravel\Contracts\AuditableContract;
use Flytedan\DanxLaravel\Traits\AuditableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class Agent extends Model implements AuditableContract
{
    use HasFactory, AuditableTrait, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'api',
        'model',
        'temperature',
        'prompt',
        'tools',
    ];

    public function casts()
    {
        return [
            'tools'       => 'json',
            'temperature' => 'float',
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

    public function formatTools()
    {
        $availableTools = collect(config('ai.tools'))->keyBy('name');
        $tools          = [];
        foreach(($this->tools ?: []) as $name) {
            $availableTool = $availableTools->get($name);
            if (!$availableTool) {
                continue;
            }
            $tools[] = [
                'type'     => 'function',
                'function' => $availableTool,
            ];
        }

        return $tools;
    }

    public function getModelApi(): AgentApiContract
    {
        $apiClass = config('ai.apis')[$this->api] ?? null;
        if (!$apiClass) {
            throw new Exception('API class not found for ' . $this->api);
        }

        return new $apiClass();
    }

    /**
     * @return static
     * @throws ValidationException
     */
    public function validate(): static
    {
        validator($this->toArray(), [
            'name'        => [
                'required',
                'max:80',
                'string',
                Rule::unique('agents')->where('team_id', $this->team_id)->whereNull('deleted_at')->ignore($this),
            ],
            'api'         => 'required|string',
            'model'       => 'required|string',
            'temperature' => 'required|numeric',
            'tools'       => 'nullable|array',
        ])->validate();

        return $this;
    }

    public static function booted()
    {
        static::creating(function (Agent $agent) {
            $agent->team_id = $agent->team_id ?? user()->team_id;
        });
    }
}
