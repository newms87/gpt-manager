<?php

namespace App\Models\Workflow;

use App\Models\ContentSource\ContentSource;
use App\Traits\HasObjectTags;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Models\Utilities\StoredFile;
use Newms87\Danx\Traits\AuditableTrait;
use Newms87\Danx\Traits\HasRelationCountersTrait;

class WorkflowInput extends Model implements AuditableContract
{
    use HasFactory, AuditableTrait, HasObjectTags, SoftDeletes, HasRelationCountersTrait;

    protected $fillable = [
        'name',
        'description',
        'content',
    ];

    public array $relationCounters = [
        WorkflowRun::class => ['workflowRuns' => 'workflow_runs_count'],
    ];

    public function casts()
    {
        return [
            'data' => 'json',
        ];
    }

    public function contentSource(): BelongsTo|ContentSource
    {
        return $this->belongsTo(ContentSource::class);
    }

    public function storedFiles(): StoredFile|MorphToMany
    {
        return $this->morphToMany(StoredFile::class, 'storable', 'stored_file_storables')->withTimestamps();
    }

    public function workflowRuns(): HasMany|WorkflowRun
    {
        return $this->hasMany(WorkflowRun::class);
    }

    public function activeWorkflowRuns(): HasMany|WorkflowRun
    {
        return $this->workflowRuns()->where(function (Builder $builder) {
            $builder->whereIn('status', [WorkflowRun::STATUS_PENDING, WorkflowRun::STATUS_RUNNING])
                ->orWhereHas('runningJobRuns');
        });
    }

    public function validate(): static
    {
        Validator::make($this->getAttributes(), [
            'name' => [
                'required',
                'max:80',
                'string',
                Rule::unique('workflow_inputs')->where('team_id', $this->team_id)->whereNull('deleted_at')->ignore($this),
            ],
        ])->validate();

        return $this;
    }

    public function __toString()
    {
        $contentLength = strlen($this->content);
        $dataLength    = strlen(json_encode($this->data));
        $filesCount    = $this->storedFiles()->count();

        return "<WorkflowInput id='$this->id' content='$contentLength bytes' data='$dataLength bytes' files='$filesCount'>";
    }
}
