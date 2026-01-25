<?php

namespace App\Services\Template;

use App\Models\TeamObject\TeamObject;
use App\Models\Template\TemplateDefinition;
use App\Services\Demand\TemplateVariableResolutionService;
use Newms87\Danx\Traits\HasDebugLogging;
use Exception;
use Illuminate\Support\Collection;

/**
 * Orchestrates template rendering by:
 * 1. Resolving template variables
 * 2. Delegating to type-specific rendering services
 * 3. Returning unified TemplateRenderResult
 */
class TemplateRenderingService
{
    use HasDebugLogging;

    /**
     * Render a template with the provided artifacts and context
     *
     * @param  TemplateDefinition  $template  The template to render
     * @param  Collection  $artifacts  Input artifacts for variable resolution
     * @param  TeamObject|null  $teamObject  Optional team object for variable resolution
     * @param  int  $teamId  Team ID for context and ownership
     *
     * @throws Exception
     */
    public function render(
        TemplateDefinition $template,
        Collection $artifacts,
        ?TeamObject $teamObject,
        int $teamId,
    ): TemplateRenderResult {
        static::logDebug('Starting template rendering', [
            'template_id'     => $template->id,
            'template_type'   => $template->type,
            'artifacts_count' => $artifacts->count(),
            'team_object_id'  => $teamObject?->id,
            'team_id'         => $teamId,
        ]);

        // Step 1: Resolve template variables
        $templateVariables = $template->templateVariables;
        $resolution        = app(TemplateVariableResolutionService::class)->resolveVariables(
            $templateVariables,
            $artifacts,
            $teamObject,
            $teamId
        );

        static::logDebug('Variables resolved', [
            'values_count' => count($resolution['values']),
            'title'        => $resolution['title'],
        ]);

        // Step 2: Delegate to type-specific service
        return match ($template->type) {
            TemplateDefinition::TYPE_GOOGLE_DOCS => $this->renderGoogleDocs($template, $resolution, $teamId),
            TemplateDefinition::TYPE_HTML        => $this->renderHtml($template, $resolution),
            default                              => throw new Exception("Unsupported template type: {$template->type}"),
        };
    }

    /**
     * Render a Google Docs template
     */
    protected function renderGoogleDocs(
        TemplateDefinition $template,
        array $resolution,
        int $teamId,
    ): TemplateRenderResult {
        $result = app(GoogleDocsRenderingService::class)->render(
            $template,
            $resolution['values'],
            $resolution['title'],
            $teamId
        );

        return TemplateRenderResult::googleDocs(
            title: $result['title'],
            values: $resolution['values'],
            url: $result['url'],
            documentId: $result['document_id'],
            storedFile: $result['stored_file'],
        );
    }

    /**
     * Render an HTML template
     */
    protected function renderHtml(
        TemplateDefinition $template,
        array $resolution,
    ): TemplateRenderResult {
        $result = app(HtmlRenderingService::class)->render(
            $template,
            $resolution['values']
        );

        return TemplateRenderResult::html(
            title: $resolution['title'] ?: $template->name,
            values: $resolution['values'],
            html: $result['html'],
            css: $result['css'],
        );
    }
}
