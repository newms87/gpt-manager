<?php

namespace App\Jobs;

use App\Models\Agent\AgentThread;
use App\Models\Template\TemplateDefinition;
use App\Services\Template\TemplatePlanningService;
use Newms87\Danx\Traits\HasDebugLogging;
use Newms87\Danx\Jobs\Job;

class TemplatePlanningJob extends Job
{
    use HasDebugLogging;

    public int $timeout = 600;

    public bool $failOnTimeout = true;

    public int $tries = 1;

    public function __construct(
        public TemplateDefinition $template,
        public string $userMessage,
        public AgentThread $thread,
        public ?string $effort = null
    ) {
        static::logDebug("TemplatePlanningJob created for template {$template->id}", [
            'effort' => $effort,
        ]);
        parent::__construct();
    }

    public function ref(): string
    {
        return 'template-planning:' . $this->template->id;
    }

    public function run(): void
    {
        app(TemplatePlanningService::class)->plan(
            $this->template,
            $this->userMessage,
            $this->thread,
            $this->effort
        );
    }
}
