<?php

namespace App\Models\Task;

use App\Models\Schema\SchemaDefinition;
use App\Services\JsonSchema\JsonSchemaService;
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
use Newms87\Danx\Traits\KeywordSearchTrait;

class Artifact extends Model implements AuditableContract
{
    use HasFactory, AuditableTrait, ActionModelTrait, KeywordSearchTrait, SoftDeletes;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected array $keywordFields = [
        'name',
        'text_content',
        'json_content',
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

    public function storedFiles(): MorphToMany|StoredFile
    {
        return $this->morphToMany(StoredFile::class, 'storable', 'stored_file_storables')->withTimestamps();
    }

    public function getJsonFragment(string $fragmentSelector = null): array
    {
        return app(JsonSchemaService::class)->filterDataByFragmentSelector($this->json_content, $fragmentSelector);
    }

    public function getMetaFragment(string $fragmentSelector = null): array
    {
        return app(JsonSchemaService::class)->filterDataByFragmentSelector($this->meta, $fragmentSelector);
    }

    public function getFlattenedJsonFragmentValues(array $fragmentSelector = []): array
    {
        return app(JsonSchemaService::class)->flattenByFragmentSelector($this->json_content, $fragmentSelector);
    }

    public function getFlattenedMetaFragmentValues(array $fragmentSelector = []): array
    {
        return app(JsonSchemaService::class)->flattenByFragmentSelector($this->meta, $fragmentSelector);
    }

    public function getFlattenedJsonFragmentValuesString(array $fragmentSelector = []): string
    {
        return implode('|', $this->getFlattenedJsonFragmentValues($fragmentSelector));
    }

    public function getFlattenedMetaFragmentValuesString(array $fragmentSelector = []): string
    {
        return implode('|', $this->getFlattenedMetaFragmentValues($fragmentSelector));
    }

    public function __toString()
    {
        $textLength = strlen($this->text_content);
        $jsonLength = strlen(json_encode($this->json_content));
        $filesCount = $this->storedFiles()->count();

        return "<Artifact ($this->id) name='$this->name' text='$textLength bytes' json='$jsonLength bytes' files='$filesCount'>";
    }
}
