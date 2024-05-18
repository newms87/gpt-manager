<?php

namespace App\Models\Shared;

use App\Models\Workflow\WorkflowRun;
use Flytedan\DanxLaravel\Contracts\AuditableContract;
use Flytedan\DanxLaravel\Models\Utilities\StoredFile;
use Flytedan\DanxLaravel\Traits\AuditableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class InputSource extends Model implements AuditableContract
{
    use HasFactory, AuditableTrait, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'content',
    ];

    public function casts()
    {
        return [
            'data' => 'json',
        ];
    }

    public function storedFiles(): StoredFile|MorphToMany
    {
        return $this->morphToMany(StoredFile::class, 'storable', 'stored_file_storables');
    }

    public function workflowRuns(): HasMany|WorkflowRun
    {
        return $this->hasMany(WorkflowRun::class);
    }

    public function activeWorkflowRuns(): HasMany|WorkflowRun
    {
        return $this->workflowRuns()->whereIn('status', [WorkflowRun::STATUS_PENDING, WorkflowRun::STATUS_RUNNING]);
    }

    public function validate(): static
    {
        Validator::make($this->getAttributes(), [
            'name' => [
                'required',
                'max:80',
                'string',
                Rule::unique('input_sources')->where('team_id', $this->team_id)->whereNull('deleted_at')->ignore($this),
            ],
        ])->validate();

        return $this;
    }
}
