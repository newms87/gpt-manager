<?php

namespace App\Jobs;

use App\Models\Agent\AgentThread;
use App\Services\Template\TemplateCollaborationService;
use App\Traits\HasDebugLogging;
use Newms87\Danx\Helpers\LockHelper;
use Newms87\Danx\Jobs\Job;
use Newms87\Danx\Models\Utilities\StoredFile;

class TemplateCollaborationJob extends Job
{
    use HasDebugLogging;

    public int $timeout = 300;

    public bool $failOnTimeout = true;

    public int $tries = 1;

    public function __construct(
        public AgentThread $thread,
        public string $message,
        public ?int $attachmentId = null,
        public bool $skipAddMessage = false
    ) {
        static::logDebug("TemplateCollaborationJob created for thread {$thread->id}");
        parent::__construct();
    }

    public function ref(): string
    {
        return 'template-collaboration:' . $this->thread->id;
    }

    public function run(): void
    {
        $lockKey = 'template-chat:' . $this->thread->collaboratable_id;

        try {
            LockHelper::acquire($lockKey, 300);

            $attachment = $this->attachmentId ? StoredFile::find($this->attachmentId) : null;

            app(TemplateCollaborationService::class)->processMessage(
                $this->thread,
                $this->message,
                $attachment,
                $this->skipAddMessage
            );
        } finally {
            LockHelper::release($lockKey);
        }
    }
}
