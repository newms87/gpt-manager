<?php

namespace App\Models\TeamObject;

use App\Models\Agent\AgentThreadMessage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Models\Utilities\StoredFile;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;

class TeamObjectAttributeSource extends Model implements AuditableContract
{
    use ActionModelTrait, AuditableTrait, HasFactory, SoftDeletes;

    protected $table   = 'team_object_attribute_sources';

    protected $guarded = [
        'id',
        'team_object_attribute_id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function sourceFile(): BelongsTo|StoredFile
    {
        return $this->belongsTo(StoredFile::class, 'stored_file_id');
    }

    public function sourceMessage(): BelongsTo|AgentThreadMessage
    {
        return $this->belongsTo(AgentThreadMessage::class, 'agent_thread_message_id');
    }

    public function __toString(): string
    {
        return "<TeamObjectAttributeSource attribute_id='$this->team_object_attribute_id' source_type='$this->source_type' source_id='$this->source_id' />";
    }
}
