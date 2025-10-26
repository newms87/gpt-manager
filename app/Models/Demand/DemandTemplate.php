<?php

namespace App\Models\Demand;

use App\Models\User;
use App\Rules\TeamScopedUniqueRule;
use App\Services\GoogleDocs\GoogleDocsFileService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Models\Utilities\StoredFile;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;

class DemandTemplate extends Model implements AuditableContract
{
    use HasFactory, SoftDeletes, ActionModelTrait, AuditableTrait;

    protected $fillable = [
        'team_id',
        'user_id',
        'stored_file_id',
        'name',
        'description',
        'category',
        'metadata',
        'is_active',
    ];

    protected $casts = [
        'metadata'  => 'array',
        'is_active' => 'boolean',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Team\Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function storedFile(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'stored_file_id');
    }

    public function templateVariables(): HasMany
    {
        return $this->hasMany(TemplateVariable::class)->orderBy('name');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForTeam($query, $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function validate(): static
    {
        validator($this->toArray(), [
            'team_id'        => ['required', 'exists:teams,id'],
            'user_id'        => ['required', 'exists:users,id'],
            'stored_file_id' => ['nullable', 'exists:stored_files,id'],
            'name'           => [
                'required',
                'string',
                'max:255',
                TeamScopedUniqueRule::make('demand_templates', 'name')->ignore($this),
            ],
            'description'    => ['nullable', 'string'],
            'category'       => ['nullable', 'string', 'max:255'],
            'metadata'       => ['nullable', 'array'],
            'is_active'      => ['boolean'],
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
