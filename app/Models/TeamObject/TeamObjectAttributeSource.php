<?php

namespace App\Models\TeamObject;

use App\Models\Agent\Message;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Models\Utilities\StoredFile;
use Newms87\Danx\Traits\AuditableTrait;

/**
 * @property int         $id
 * @property int         $object_attribute_id
 * @property string      $source_type
 * @property string      $source_id
 * @property string|null $explanation
 * @property string      $stored_file_id
 * @property int         $message_id
 * @property Carbon      $created_at
 * @property Carbon      $updated_at
 * @property Carbon      $deleted_at
 * @property StoredFile  $sourceFile
 * @property Message     $message
 */
class TeamObjectAttributeSource extends Model implements AuditableContract
{
    use AuditableTrait, SoftDeletes;

    protected $table   = 'team__object_attribute_sources';
    protected $guarded = [
        'id',
        'object_attribute_id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function __construct(array $attributes = [])
    {
        if (!team()->namespace) {
            throw new Exception("Cannot instantiate " . static::class . ": Team namespace is not set");
        }

        $this->table = team()->namespace . '__object_attribute_sources';

        parent::__construct($attributes);
    }

    public function sourceFile(): BelongsTo|StoredFile
    {
        return $this->belongsTo(StoredFile::class, 'stored_file_id');
    }

    public function sourceMessage(): BelongsTo|Message
    {
        return $this->belongsTo(Message::class, 'message_id');
    }

    public function __toString(): string
    {
        return "<TeamObjectAttributeSource object_attribute_id='$this->object_attribute_id' source_type='$this->source_type' source_id='$this->source_id' />";
    }

}
