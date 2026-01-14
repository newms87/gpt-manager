<?php

namespace App\Events;

use App\Models\Template\TemplateDefinition;
use App\Resources\Template\TemplateDefinitionResource;
use Newms87\Danx\Events\ModelSavedEvent;

class TemplateDefinitionUpdatedEvent extends ModelSavedEvent
{
    public function __construct(protected TemplateDefinition $templateDefinition, protected string $event)
    {
        parent::__construct(
            $templateDefinition,
            $event,
            TemplateDefinitionResource::class,
            $templateDefinition->team_id
        );
    }

    protected function createdData(): array
    {
        return TemplateDefinitionResource::make($this->templateDefinition, [
            '*'         => false,
            'name'      => true,
            'type'      => true,
            'timestamp' => true,
        ]);
    }

    protected function updatedData(): array
    {
        return TemplateDefinitionResource::make($this->templateDefinition, [
            '*'                       => false,
            'name'                    => true,
            'html_content'            => true,
            'css_content'             => true,
            'building_job_dispatch'   => true,
            'pending_build_context'   => true,
            'timestamp'               => true,
        ]);
    }
}
