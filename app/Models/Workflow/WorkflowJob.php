<?php

namespace App\Models\Workflow;

use App\Models\Agent\Agent;
use Flytedan\DanxLaravel\Contracts\AuditableContract;
use Flytedan\DanxLaravel\Traits\AuditableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkflowJob extends Model implements AuditableContract
{
    use HasFactory, AuditableTrait;

    public function workflow()
    {
        return $this->belongsTo(Workflow::class);
    }

    public function workflowJobRuns()
    {
        return $this->hasMany(WorkflowJobRun::class);
    }

    public function agents()
    {
        return $this->belongsToMany(Agent::class);
    }
}
