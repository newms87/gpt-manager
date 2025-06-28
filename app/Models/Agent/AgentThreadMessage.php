<?php

namespace App\Models\Agent;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Helpers\StringHelper;
use Newms87\Danx\Models\Utilities\StoredFile;
use Newms87\Danx\Traits\ActionModelTrait;
use Newms87\Danx\Traits\AuditableTrait;

class AgentThreadMessage extends Model implements AuditableContract
{
    use HasFactory, AuditableTrait, SoftDeletes, ActionModelTrait;

    const string
        ROLE_USER = 'user',
        ROLE_ASSISTANT = 'assistant';

    protected $fillable = [
        'role',
        'title',
        'summary',
        'content',
        'data',
        'api_response_id',
    ];

    public function casts()
    {
        return [
            'data' => 'json',
        ];
    }

    public function agentThread(): BelongsTo|AgentThread
    {
        return $this->belongsTo(AgentThread::class, 'agent_thread_id');
    }

    public function storedFiles(): MorphToMany|StoredFile
    {
        return $this->morphToMany(StoredFile::class, 'storable', 'stored_file_storables')->withTimestamps();
    }

    public function isUser(): bool
    {
        return $this->role === self::ROLE_USER;
    }

    /**
     * Cleans the AI Model responses to make sure we have valid JSON, if the response is JSON
     */
    public function getCleanContent(): string
    {
        // Remove any ```json and trailing ``` from content if they are present
        $content = preg_replace('/^```[a-z]+\n(.*)\n```$/s', '$1', trim($this->content ?? ''));

        // XXX: Special case for perplexity that sometimes returns the same response multiple times prefixing subsequent responses with }assistant\n\n{...
        return preg_replace("/}assistant\s*\{/", '', $content);
    }

    public function getJsonContent(): array|string|null
    {
        $content = $this->getCleanContent();

        $result = json_decode($content, true);

        if ($result) {
            return is_array($result) ? $result : ['text_content' => (string)$result];
        }

        return ['text_content' => $content];
    }

    /**
     * Set the API response ID for this message
     */
    public function setApiResponseId(string $responseId): void
    {
        $this->api_response_id = $responseId;
        $this->save();
    }

    public function __toString()
    {
        $message = StringHelper::limitText(20, $this->title ?: $this->content) ?: '(Empty)';

        return "<AgentThreadMessage ($this->id) $message>";
    }

    public static function booted(): void
    {
        static::saved(function (AgentThreadMessage $message) {
            // Touch the parent thread to trigger its updated event
            if ($message->agentThread) {
                $message->agentThread->touch();
            }
        });
    }
}
