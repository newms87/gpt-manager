<?php

namespace App\Models\Task;

use App\Models\Schema\SchemaDefinition;
use App\Services\JsonSchema\JsonSchemaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Models\Utilities\StoredFile;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;
use Newms87\Danx\Traits\HasRelationCountersTrait;
use Newms87\Danx\Traits\KeywordSearchTrait;

class Artifact extends Model implements AuditableContract
{
    use ActionModelTrait, AuditableTrait, HasFactory, HasRelationCountersTrait, KeywordSearchTrait, SoftDeletes;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected array $keywordFields = [
        'id',
        'name',
        'text_content',
        'json_content',
        'meta',
        'position',
        'model',
    ];

    public array $relationCounters = [
        Artifact::class => ['children' => 'child_artifacts_count'],
    ];

    public function casts(): array
    {
        return [
            'json_content' => 'json',
            'meta'         => 'json',
        ];
    }

    public function canView(): bool
    {
        if (!$this->schema_definition_id) {
            return true;
        }

        return $this->schemaDefinition()->withTrashed()->first()->canView();
    }

    public function original(): BelongsTo|Artifact
    {
        return $this->belongsTo(Artifact::class, 'original_artifact_id');
    }

    public function parent(): BelongsTo|Artifact
    {
        return $this->belongsTo(Artifact::class, 'parent_artifact_id');
    }

    public function children(): HasMany|Artifact
    {
        return $this->hasMany(Artifact::class, 'parent_artifact_id');
    }

    public function schemaDefinition(): BelongsTo|SchemaDefinition
    {
        return $this->belongsTo(SchemaDefinition::class);
    }

    public function taskDefinition(): BelongsTo|TaskDefinition
    {
        return $this->belongsTo(TaskDefinition::class);
    }

    public function artifactables(): HasMany|Artifactable
    {
        return $this->hasMany(Artifactable::class);
    }

    public function taskRun(): BelongsTo|TaskRun
    {
        return $this->belongsTo(TaskRun::class);
    }

    public function taskProcess(): BelongsTo|TaskProcess
    {
        return $this->belongsTo(TaskProcess::class);
    }

    public function scopeTaskRun(Builder $query, $filter): Builder
    {
        return $query->whereHas('artifactables', fn(Builder $q) => $q->where($filter)
            ->where('artifactable_type', TaskRun::class)
        );
    }

    public function scopeTaskProcess(Builder $query, $filter): Builder
    {
        return $query->whereHas('artifactables', fn(Builder $q) => $q->where($filter)
            ->where('artifactable_type', TaskProcess::class)
        );
    }

    public function storedFiles(): MorphToMany|StoredFile
    {
        return $this->morphToMany(StoredFile::class, 'storable', 'stored_file_storables')->withTimestamps();
    }

    public function assignChildren($artifacts): static
    {
        $this->children()->saveMany($artifacts);
        $this->updateRelationCounter('children');

        return $this;
    }

    public function clearChildren(): static
    {
        $this->children()->delete();
        $this->updateRelationCounter('children');

        return $this;
    }

    /**
     * Get the fragment defined by the fragment selector for the JSON content field
     */
    public function getJsonFragment(?array $fragmentSelector = null): array
    {
        return app(JsonSchemaService::class)->filterDataByFragmentSelector($this->json_content, $fragmentSelector);
    }

    /**
     * Get the fragment defined by the fragment selector for the meta field
     */
    public function getMetaFragment(?array $fragmentSelector = null): array
    {
        return app(JsonSchemaService::class)->filterDataByFragmentSelector($this->meta, $fragmentSelector);
    }

    /**
     * Get the first value of the JSON fragment.
     */
    public function getJsonFragmentValue(?array $fragmentSelector = null): mixed
    {
        $values = $this->getFlattenedJsonFragmentValues($fragmentSelector);

        $types = app(JsonSchemaService::class)->getFragmentSelectorLeafTypes($fragmentSelector);

        if (in_array('array', $types)) {
            return implode('|', $values) ?: null;
        }

        return $values[0] ?? null;
    }

    /**
     * Get the first value of the meta fragment.
     */
    public function getMetaFragmentValue(?array $fragmentSelector = null): mixed
    {
        $values = $this->getFlattenedMetaFragmentValues($fragmentSelector);

        $types = app(JsonSchemaService::class)->getFragmentSelectorLeafTypes($fragmentSelector);

        if (in_array('array', $types)) {
            return implode('|', $values) ?: null;
        }

        return $values[0] ?? null;
    }

    /**
     * Get an array of all the values of the leaf nodes in a fragment selector for the JSON content field
     */
    public function getFlattenedJsonFragmentValues(array $fragmentSelector = []): array
    {
        return app(JsonSchemaService::class)->flattenByFragmentSelector($this->json_content ?: [], $fragmentSelector);
    }

    /**
     * Get an array of all the values of the leaf nodes in a fragment selector for the meta field
     */
    public function getFlattenedMetaFragmentValues(array $fragmentSelector = []): array
    {
        return app(JsonSchemaService::class)->flattenByFragmentSelector($this->meta ?: [], $fragmentSelector);
    }

    /**
     * Get a string of all the values of the leaf nodes in a fragment selector for the JSON content field
     */
    public function getFlattenedJsonFragmentValuesString(array $fragmentSelector = []): string
    {
        return implode('|', $this->getFlattenedJsonFragmentValues($fragmentSelector));
    }

    /**
     * Get a string of all the values of the leaf nodes in a fragment selector for the meta field
     */
    public function getFlattenedMetaFragmentValuesString(array $fragmentSelector = []): string
    {
        return implode('|', $this->getFlattenedMetaFragmentValues($fragmentSelector));
    }

    public static function booted()
    {
        static::saving(function (Artifact $artifact) {
            if (!$artifact->team_id) {
                $artifact->team_id = team()?->id;
            }
        });
    }

    public function __toString()
    {
        $textLength = strlen($this->text_content);
        $jsonLength = strlen(json_encode($this->json_content));
        $filesCount = $this->storedFiles()->count();

        return "<Artifact ($this->id) name='$this->name' text='$textLength bytes' json='$jsonLength bytes' files='$filesCount'>";
    }
}
