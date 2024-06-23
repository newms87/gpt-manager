<?php

namespace App\Models\Agent;

use App\Models\Team\Team;
use App\Repositories\AgentRepository;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use Newms87\Danx\Contracts\AuditableContract;
use Newms87\Danx\Services\TranscodeFileService;
use Newms87\Danx\Traits\AuditableTrait;
use Newms87\Danx\Traits\CountableTrait;

class Thread extends Model implements AuditableContract
{
    use HasFactory, AuditableTrait, SoftDeletes, CountableTrait;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected array $usage = [];

    public array $relatedCounters = [
        Agent::class => 'threads_count',
    ];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function runs()
    {
        return $this->hasMany(ThreadRun::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * Format the messages to be sent to an AI completion API
     * @return array
     * @throws Exception
     */
    public function getMessagesForApi(): array
    {
        $corePrompt = "The current date and time is " . now()->toDateTimeString() . "\n\n";

        $messages = collect([
            [
                'role'    => Message::ROLE_USER,
                'content' => $corePrompt . $this->agent->prompt,
            ],
        ]);

        foreach($this->messages()->get() as $message) {
            $content = $message->content;
            // If first and last character of the message is a [ and ] then json decode the message as its an array of message elements (ie: text or image_url)
            if (in_array(substr($content, 0, 1), ['[']) && in_array(substr($message->content, -1), [']'])) {
                $content = json_decode($content, true);
            }
            $files = $message->storedFiles()->get();

            // Add Image URLs to the content
            if ($files->isNotEmpty()) {
                if (is_string($content)) {
                    $content = [
                        [
                            'type' => 'text',
                            'text' => $content,
                        ],
                    ];
                }

                foreach($files as $file) {
                    if ($file->isImage()) {
                        $content[] = [
                            'type'      => 'image_url',
                            'image_url' => ['url' => $file->url],
                        ];
                    } elseif ($file->isPdf()) {
                        $transcodes = $file->transcodes()->where('transcode_name', TranscodeFileService::TRANSCODE_PDF_TO_IMAGES)->get();

                        foreach($transcodes as $transcode) {
                            $content[] = [
                                'type'      => 'image_url',
                                'image_url' => ['url' => $transcode->url],
                            ];
                            Log::debug("$message appending transcoded file $transcode->url");
                        }
                    } else {
                        throw new Exception('Only images are supported for now.');
                    }
                }
            }

            $messages->push([
                    'role'    => $message->role,
                    'content' => $content,
                ] + ($message->data ?? []));
        }

        return $messages->toArray();
    }

    public function getTotalInputTokens()
    {
        if (isset($this->usage['input_tokens'])) {
            return $this->usage['input_tokens'];
        }

        return $this->usage['input_tokens'] = $this->runs()->sum('input_tokens');
    }

    public function getTotalOutputTokens()
    {
        if (isset($this->usage['output_tokens'])) {
            return $this->usage['output_tokens'];
        }

        return $this->usage['output_tokens'] = $this->runs()->sum('output_tokens');
    }

    public function getTotalCost()
    {
        if (isset($this->usage['cost'])) {
            return $this->usage['cost'];
        }

        $inputTokens  = $this->getTotalInputTokens();
        $outputTokens = $this->getTotalOutputTokens();

        $cost = app(AgentRepository::class)->calcTotalCost($this->agent, $inputTokens, $outputTokens);

        return $this->usage['cost'] = $cost;
    }

    public function __toString()
    {
        return "<Thread ($this->id) $this->name>";
    }
}
