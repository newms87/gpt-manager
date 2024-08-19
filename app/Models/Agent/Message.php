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

    public function isUser(): bool
    {
        return $this->role === self::ROLE_USER;
    }

    public function isTool(): bool
    {
        return $this->role === self::ROLE_TOOL;
    }

    /**
     * Cleans the AI Model responses to make sure we have valid JSON, if the response is JSON
     */
    public function getCleanContent(): string
    {
        // Remove any ```json and trailing ``` from content if they are present
        $content = preg_replace('/^```json\n(.*)\n```$/s', '$1', trim($this->content ?? ''));

        // XXX: Special case for perplexity that sometimes returns the same response multiple times prefixing subsequent responses with }assistant\n\n{...
        return preg_replace("/}assistant\s*\{/", '', $content);
    }

    public function getJsonContent(): ?array
    {
        return json_decode($this->getCleanContent(), true);
    }

    public function __toString()
    {
        $message = StringHelper::limitText(20, $this->title ?: $this->content) ?: '(Empty)';

        return "<Message ($this->id) $message>";
    }
}
