<?php

namespace App\Models\Workflow;

use App\Models\Agent\Agent;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Traits\AuditableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkflowAssignment extends Model implements AuditableContract
{
    use HasFactory, SoftDeletes, AuditableTrait;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    public function workflowJob()
    {
        return $this->belongsTo(WorkflowJob::class);
    }

    public function workflowTasks()
    {
        return $this->hasMany(WorkflowTask::class);
    }
}
