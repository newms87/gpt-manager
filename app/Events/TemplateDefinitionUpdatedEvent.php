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
            '*'    => false,
            'name' => true,
            'type' => true,
        ]);
    }

    protected function updatedData(): array
    {
        return TemplateDefinitionResource::make($this->templateDefinition, [
            '*'                        => false,
            'name'                     => true,
            'building_job_dispatch_id' => true,
        ]) + [
            // Add a simple boolean flag instead of sending the full pending context array
            'has_pending_build' => !empty($this->templateDefinition->pending_build_context),
        ];
    }
}
