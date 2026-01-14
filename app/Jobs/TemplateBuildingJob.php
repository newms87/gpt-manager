<?php

namespace App\Jobs;

use App\Models\Template\TemplateDefinition;
use App\Services\Template\TemplateBuildingService;
use App\Traits\HasDebugLogging;
use Newms87\Danx\Jobs\Job;

class TemplateBuildingJob extends Job
{
    use HasDebugLogging;

    public int $timeout = 600;

    public bool $failOnTimeout = true;

    public int $tries = 1;

    public function __construct(
        public TemplateDefinition $template,
        public string $buildContext
    ) {
        static::logDebug("TemplateBuildingJob created for template {$template->id}");
        parent::__construct();
    }

    public function ref(): string
    {
        return 'template-building:' . $this->template->id;
    }

    public function run(): void
    {
        app(TemplateBuildingService::class)->build($this->template, $this->buildContext);
    }
}
