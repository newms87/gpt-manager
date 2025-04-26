<?php

namespace App\Models\Agent;

use App\Events\AgentThreadRunUpdatedEvent;
use App\Models\Schema\SchemaDefinition;
use App\Models\Schema\SchemaFragment;
use App\Services\JsonSchema\JsonSchemaService;
use App\Traits\HasWorkflowStatesTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Models\Job\JobDispatch;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;

class AgentThreadRun extends Model implements AuditableContract
{
    use HasFactory, AuditableTrait, SoftDeletes, ActionModelTrait, HasWorkflowStatesTrait;

    const string
        RESPONSE_FORMAT_TEXT = 'text',
        RESPONSE_FORMAT_JSON_SCHEMA = 'json_schema';

    const string
        STATUS_RUNNING = 'Running',
        STATUS_COMPLETED = 'Completed',
        STATUS_STOPPED = 'Stopped',
        STATUS_FAILED = 'Failed';

    protected $fillable = [
        'agent_model',
        'completed_at',
        'failed_at',
        'input_tokens',
        'last_message_id',
        'output_tokens',
        'refreshed_at',
        'response_format',
        'response_schema_id',
        'response_fragment_id',
        'json_schema_config',
        'response_json_schema',
        'seed',
        'started_at',
        'status',
        'temperature',
        'tool_choice',
        'tools',
        'total_cost',
    ];

    public function casts(): array
    {
        return [
            'tools'                => 'json',
            'json_schema_config'   => 'json',
            'response_json_schema' => 'json',
            'temperature'          => 'float',
            'started_at'           => 'datetime',
            'stopped_at'           => 'datetime',
            'completed_at'         => 'datetime',
            'failed_at'            => 'datetime',
            'refreshed_at'         => 'datetime',
        ];
    }

    public function getDateFormat(): string
    {
        return 'Y-m-d H:i:s.v';
    }

    public function agentThread(): AgentThread|BelongsTo
    {
        return $this->belongsTo(AgentThread::class, 'agent_thread_id');
    }

    public function lastMessage(): BelongsTo|AgentThreadMessage
    {
        return $this->belongsTo(AgentThreadMessage::class, 'last_message_id');
    }

    public function jobDispatch(): BelongsTo|JobDispatch
    {
        return $this->belongsTo(JobDispatch::class);
    }

    public function responseSchema(): BelongsTo|SchemaDefinition
    {
        return $this->belongsTo(SchemaDefinition::class, 'response_schema_id');
    }

    public function responseFragment(): BelongsTo|SchemaFragment
    {
        return $this->belongsTo(SchemaFragment::class, 'response_fragment_id');
    }

    public function getJsonSchemaService(): JsonSchemaService
    {
        return app(JsonSchemaService::class)->setConfig($this->json_schema_config);
    }

    public function renderResponseJsonSchema(string $name, array $schema, array $fragmentSelector = null): ?array
    {
        return $this->getJsonSchemaService()->formatAndFilterSchema($name, $schema, $fragmentSelector);
    }

    public static function booted(): void
    {
        static::saving(function (AgentThreadRun $agentThreadRun) {
            if ($agentThreadRun->isDirty(['started_at', 'failed_at', 'completed_at', 'stopped_at'])) {
                $agentThreadRun->computeStatus();
            }
        });
        static::saved(function (AgentThreadRun $agentThreadRun) {
            if ($agentThreadRun->wasChanged(['status', 'last_message_id'])) {
                AgentThreadRunUpdatedEvent::dispatch($agentThreadRun);
            }
        });
    }

    public function __toString(): string
    {
        return "<AgentThreadRun $this->id $this->status thread='{$this->agentThread->name}'>";
    }
}
