<?php

namespace App\Models\Workflow;

use Flytedan\DanxLaravel\Contracts\AuditableContract;
use Flytedan\DanxLaravel\Traits\AuditableTrait;
use Flytedan\DanxLaravel\Traits\CountableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkflowJob extends Model implements AuditableContract
{
    use HasFactory, SoftDeletes, AuditableTrait, CountableTrait;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public array $relatedCounters = [
        Workflow::class => 'jobs_count',
    ];

    public function casts()
    {
        return [
            'config' => 'array',
        ];
    }

    public function workflow()
    {
        return $this->belongsTo(Workflow::class);
    }

    public function workflowTasks()
    {
        return $this->hasMany(WorkflowTask::class);
    }

    public function workflowAssignments()
    {
        return $this->hasMany(WorkflowAssignment::class);
    }
}
