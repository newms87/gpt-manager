<?php

namespace App\Models\Agent;

use App\Models\ResourcePackage\ResourcePackageableContract;
use App\Models\ResourcePackage\ResourcePackageableTrait;
use App\Models\Team\Team;
use App\Services\Workflow\WorkflowExportService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\Rule;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Helpers\StringHelper;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;
use Newms87\Danx\Traits\KeywordSearchTrait;

class McpServer extends Model implements AuditableContract, ResourcePackageableContract
{
    use HasFactory, AuditableTrait, SoftDeletes, ResourcePackageableTrait, KeywordSearchTrait, ActionModelTrait;

    protected static function newFactory()
    {
        return \Database\Factories\McpServerFactory::new();
    }

    protected $fillable = [
        'name',
        'label',
        'description',
        'server_url',
        'headers',
        'allowed_tools',
        'require_approval',
        'is_active',
    ];

    protected array $keywordFields = [
        'name',
        'label',
        'description',
        'server_url',
    ];

    public function casts(): array
    {
        return [
            'headers' => 'json',
            'allowed_tools' => 'json',
            'is_active' => 'boolean',
        ];
    }

    public function team(): BelongsTo|Team
    {
        return $this->belongsTo(Team::class);
    }

    public function validate(): static
    {
        validator($this->toArray(), [
            'name' => [
                'required',
                'max:80',
                'string',
            ],
            'label' => [
                'required',
                'max:80',
                'string',
                Rule::unique('mcp_servers')->where('team_id', $this->team_id)->whereNull('deleted_at')->ignore($this),
            ],
            'server_url' => 'required|url',
            'headers' => 'nullable|array',
            'allowed_tools' => 'nullable|array',
            'require_approval' => 'required|in:never,always',
            'is_active' => 'boolean',
        ])->validate();

        return $this;
    }

    public static function booted(): void
    {
        static::creating(function (McpServer $mcpServer) {
            $mcpServer->team_id = $mcpServer->team_id ?? team()->id;
        });
    }

    public function exportToJson(WorkflowExportService $service): int
    {
        return $service->register($this, [
            'name' => $this->name,
            'label' => $this->label,
            'description' => $this->description,
            'server_url' => $this->server_url,
            'headers' => $this->headers,
            'allowed_tools' => $this->allowed_tools,
            'require_approval' => $this->require_approval,
            'is_active' => $this->is_active,
        ]);
    }

    public function __toString(): string
    {
        return "<McpServer ($this->id) " . StringHelper::limitText(20, $this->name) . ": $this->server_url>";
    }
}