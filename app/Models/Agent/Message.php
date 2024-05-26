<?php

namespace App\Models\Agent;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Helpers\StringHelper;
use Newms87\Danx\Models\Utilities\StoredFile;
use Newms87\Danx\Traits\AuditableTrait;

class Message extends Model implements AuditableContract
{
    use HasFactory, AuditableTrait, SoftDeletes;

    const string
        ROLE_USER = 'user',
        ROLE_ASSISTANT = 'assistant',
        ROLE_TOOL = 'tool';

    protected $fillable = [
        'role',
        'title',
        'summary',
        'content',
        'data',
    ];

    public function casts()
    {
        return [
            'data' => 'json',
        ];
    }

    public function thread()
    {
        return $this->belongsTo(Thread::class);
    }

    public function storedFiles(): MorphToMany|StoredFile
    {
        return $this->morphToMany(StoredFile::class, 'storable', 'stored_file_storables')->withTimestamps();
    }

    public function __toString()
    {
        $message = $this->title ?: $this->summary ?: StringHelper::limitText(50, $this->content) ?: '(Empty)';

        return "<Message ($this->id) $message>";
    }
}
