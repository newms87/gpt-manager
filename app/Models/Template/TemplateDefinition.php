<?php

namespace App\Models\Template;

use App\Models\Agent\AgentThread;
use App\Models\Team\Team;
use App\Models\User;
use App\Rules\TeamScopedUniqueRule;
use App\Services\GoogleDocs\GoogleDocsFileService;
use App\Traits\HasTags;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Models\Job\JobDispatch;
use Newms87\Danx\Models\Utilities\StoredFile;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;
use Newms87\Danx\Traits\HasRelationCountersTrait;

class TemplateDefinition extends Model implements AuditableContract
{
    use ActionModelTrait, AuditableTrait, HasFactory, HasRelationCountersTrait, HasTags, SoftDeletes;

    public array $relationCounters = [
        TemplateVariable::class => ['templateVariables' => 'template_variables_count'],
        JobDispatch::class      => ['jobDispatches' => 'job_dispatches_count'],
    ];

    public const string TYPE_GOOGLE_DOCS = 'google_docs',
        TYPE_HTML                        = 'html';

    protected $table = 'template_definitions';

    protected $fillable = [
        'team_id',
        'user_id',
        'type',
        'stored_file_id',
        'name',
        'description',
        'category',
        'metadata',
        'html_content',
        'css_content',
        'preview_stored_file_id',
        'building_job_dispatch_id',
        'pending_build_context',
        'is_active',
    ];

    protected $casts = [
        'metadata'              => 'array',
        'pending_build_context' => 'array',
        'is_active'             => 'boolean',
    ];

    protected static function booted(): void
    {
        static::updating(function (TemplateDefinition $template) {
            $dirty = $template->getDirty();

            if (isset($dirty['html_content']) || isset($dirty['css_content'])) {
                TemplateDefinitionHistory::write($template);
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function storedFile(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'stored_file_id');
    }

    public function previewStoredFile(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'preview_stored_file_id');
    }

    public function buildingJobDispatch(): BelongsTo
    {
        return $this->belongsTo(JobDispatch::class, 'building_job_dispatch_id');
    }

    public function templateVariables(): HasMany
    {
        return $this->hasMany(TemplateVariable::class)->orderBy('name');
    }

    public function history(): HasMany
    {
        return $this->hasMany(TemplateDefinitionHistory::class)->orderByDesc('created_at');
    }

    public function collaborationThreads(): MorphMany
    {
        return $this->morphMany(AgentThread::class, 'collaboratable');
    }

    public function jobDispatches(): MorphToMany
    {
        return $this->morphToMany(JobDispatch::class, 'model', 'job_dispatchables')->orderByDesc('job_dispatch.id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeGoogleDocs(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_GOOGLE_DOCS);
    }

    public function scopeHtml(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_HTML);
    }

    public function isGoogleDocs(): bool
    {
        return $this->type === self::TYPE_GOOGLE_DOCS;
    }

    public function isHtml(): bool
    {
        return $this->type === self::TYPE_HTML;
    }

    /**
     * Extract variable names from data-var-* attributes in html_content.
     *
     * @return array<string>
     */
    public function extractVariableNames(): array
    {
        if (!$this->html_content) {
            return [];
        }

        preg_match_all('/data-var-([a-zA-Z0-9_-]+)/', $this->html_content, $matches);

        return array_unique($matches[1] ?? []);
    }

    public function validate(): static
    {
        validator($this->toArray(), [
            'team_id'        => ['required', 'exists:teams,id'],
            'user_id'        => ['required', 'exists:users,id'],
            'type'           => ['required', 'string', 'in:' . self::TYPE_GOOGLE_DOCS . ',' . self::TYPE_HTML],
            'stored_file_id' => ['nullable', 'exists:stored_files,id'],
            'name'           => [
                'required',
                'string',
                'max:255',
                TeamScopedUniqueRule::make('template_definitions', 'name')->ignore($this),
            ],
            'description'            => ['nullable', 'string'],
            'category'               => ['nullable', 'string', 'max:255'],
            'metadata'               => ['nullable', 'array'],
            'html_content'           => ['nullable', 'string'],
            'css_content'            => ['nullable', 'string'],
            'preview_stored_file_id' => ['nullable', 'exists:stored_files,id'],
            'is_active'              => ['boolean'],
        ])->validate();

        return $this;
    }

    public function getTemplateUrl(): ?string
    {
        return $this->storedFile?->url;
    }

    public function extractGoogleDocId(): ?string
    {
        $url = $this->getTemplateUrl();
        if (!$url) {
            return null;
        }

        return app(GoogleDocsFileService::class)->extractDocumentId($url);
    }
}
