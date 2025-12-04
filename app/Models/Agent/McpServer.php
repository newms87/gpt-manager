<?php

namespace App\Models\Agent;

use App\Models\ResourcePackage\ResourcePackageableContract;
use App\Models\ResourcePackage\ResourcePackageableTrait;
use App\Models\Team\Team;
use App\Services\Workflow\WorkflowExportServiceInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Helpers\StringHelper;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;
use Newms87\Danx\Traits\KeywordSearchTrait;

class McpServer extends Model implements AuditableContract, ResourcePackageableContract
{
    use ActionModelTrait, AuditableTrait, HasFactory, KeywordSearchTrait, ResourcePackageableTrait, SoftDeletes;

    protected static function newFactory()
    {
        return \Database\Factories\McpServerFactory::new();
    }

    protected $fillable = [
        'name',
        'description',
        'server_url',
        'headers',
        'allowed_tools',
    ];

    protected array $keywordFields = [
        'name',
        'description',
        'server_url',
    ];

    public function casts(): array
    {
        return [
            'headers'       => 'json',
            'allowed_tools' => 'json',
        ];
    }

    public function team(): BelongsTo|Team
    {
        return $this->belongsTo(Team::class);
    }

    public function validate(): static
    {
        validator($this->toArray(), [
            'name'          => [
                'required',
                'max:80',
                'string',
            ],
            'server_url'    => 'required|url',
            'headers'       => 'nullable|array',
            'allowed_tools' => 'nullable|array',
        ])->validate();

        return $this;
    }

    public static function booted(): void
    {
        static::creating(function (McpServer $mcpServer) {
            $mcpServer->team_id = $mcpServer->team_id ?? team()->id;
        });
    }

    public function exportToJson(WorkflowExportServiceInterface $service): int
    {
        return $service->register($this, [
            'name'          => $this->name,
            'description'   => $this->description,
            'server_url'    => $this->server_url,
            'headers'       => $this->headers,
            'allowed_tools' => $this->allowed_tools,
        ]);
    }

    public function __toString(): string
    {
        return "<McpServer id='$this->id' name='" . StringHelper::limitText(20, $this->name) . "' server_url='$this->server_url'>";
    }
}
