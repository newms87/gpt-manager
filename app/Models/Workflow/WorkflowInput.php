<?php

namespace App\Models\Workflow;

use App\Models\ContentSource\ContentSource;
use App\Models\Task\Artifact;
use App\Models\TeamObject\TeamObject;
use App\Services\Workflow\WorkflowInputToArtifactMapper;
use App\Traits\HasObjectTags;
use App\Traits\HasTags;
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
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;
use Newms87\Danx\Traits\KeywordSearchTrait;

class WorkflowInput extends Model implements AuditableContract
{
    use ActionModelTrait, AuditableTrait, HasFactory, HasObjectTags, HasTags, KeywordSearchTrait, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'content',
        'team_object_id',
        'team_object_type',
    ];

    protected array $keywordFields = ['name', 'description'];

    public function casts()
    {
        return [
            'data' => 'json',
        ];
    }

    public function teamObject(): BelongsTo
    {
        return $this->belongsTo(TeamObject::class, 'team_object_id');
    }

    public function contentSource(): BelongsTo|ContentSource
    {
        return $this->belongsTo(ContentSource::class);
    }

    public function storedFiles(): StoredFile|MorphToMany
    {
        return $this->morphToMany(StoredFile::class, 'storable', 'stored_file_storables')->withTimestamps();
    }

    public function validate(): static
    {
        Validator::make($this->getAttributes(), [
            'name'             => [
                'required',
                'max:80',
                'string',
                Rule::unique('workflow_inputs')->where('team_id', $this->team_id)->whereNull('deleted_at')->ignore($this),
            ],
            'team_object_id'   => [
                'nullable',
                'integer', // Adjust type as needed
            ],
            'team_object_type' => [
                // If team_object_id is present, team_object_type must be present.
                'required_with:team_object_id',
                'nullable',
                'string',
            ],
        ])->validate();

        return $this;
    }

    public function toArtifact(): Artifact
    {
        return (new WorkflowInputToArtifactMapper)->setWorkflowInput($this)->map();
    }

    public function associations(): HasMany
    {
        return $this->hasMany(WorkflowInputAssociation::class);
    }

    public function __toString()
    {
        $contentLength = strlen($this->content);
        $dataLength    = strlen(json_encode($this->data));
        $filesCount    = $this->storedFiles()->count();

        return "<WorkflowInput id='$this->id' name='$this->name' content='$contentLength bytes' data='$dataLength bytes' files='$filesCount'>";
    }
}
