<?php

namespace App\Models\TeamObject;

use App\Models\Agent\Message;
use App\Models\Agent\ThreadRun;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Models\Utilities\StoredFile;
use Newms87\Danx\Traits\AuditableTrait;

/**
 * @property int       $id
 * @property string    $object_id
 * @property string    $name
 * @property Carbon    $date
 * @property string    $description
 * @property string    $confidence
 * @property string    $text_value
 * @property array     $json_value
 * @property string    $message_id
 * @property string    $source_stored_file_id
 * @property Carbon    $created_at
 * @property Carbon    $updated_at
 * @property ThreadRun $threadRun
 */
class TeamObjectAttribute extends Model implements AuditableContract
{
    use AuditableTrait, SoftDeletes;

    protected $table   = 'team__object_attributes';
    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function __construct(array $attributes = [])
    {
        if (!team()->namespace) {
            throw new Exception("Cannot instantiate " . static::class . ": Team namespace is not set");
        }

        $this->table = team()->namespace . '__object_attributes';

        parent::__construct($attributes);
    }

    public function casts(): array
    {
        return [
            'date'       => 'datetime',
            'json_value' => 'json',
        ];
    }

    public function getValue(): string|array
    {
        return $this->text_value ?? $this->json_value;
    }

    public function sourceFile(): BelongsTo|StoredFile
    {
        return $this->belongsTo(StoredFile::class, 'source_stored_file_id');
    }

    public function sourceMessages(): MorphToMany|Message
    {
        return $this->morphToMany(Message::class, 'messageable', 'messageables')->withTimestamps();
    }

    public function threadRun(): BelongsTo|ThreadRun
    {
        return $this->belongsTo(ThreadRun::class, 'thread_run_id');
    }

    public function __toString(): string
    {
        $value = $this->text_value ?? json_encode($this->json_value);

        return "<TeamObjectAttribute ($this->object_id) name='$this->name' value='$value' />";
    }

}
