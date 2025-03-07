<?php

namespace App\Models\Task;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Models\Utilities\StoredFile;
use Newms87\Danx\Traits\AuditableTrait;

class Artifact extends Model implements AuditableContract
{
    use HasFactory, AuditableTrait, SoftDeletes;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function casts(): array
    {
        return [
            'json_content' => 'json',
        ];
    }

    public function artifactables(): HasMany|Artifactable
    {
        return $this->hasMany(Artifactable::class);
    }

    public function storedFiles(): MorphToMany|StoredFile
    {
        return $this->morphToMany(StoredFile::class, 'storable', 'stored_file_storables')->withTimestamps();
    }

    public function getTaskProcessThatCreatedArtifact(): TaskProcess|null
    {
        $artifactable = $this->artifactables()->where('category', 'output')->where('artifactable_type', TaskProcess::class)->first();

        if (!$artifactable) {
            return null;
        }

        return TaskProcess::find($artifactable->artifactable_id);
    }

    public function __toString()
    {
        $textLength = strlen($this->text_content);
        $jsonLength = strlen(json_encode($this->json_content));
        $filesCount = $this->storedFiles()->count();

        return "<Artifact ($this->id) name='$this->name' text='$textLength bytes' json='$jsonLength bytes' files='$filesCount'>";
    }
}
