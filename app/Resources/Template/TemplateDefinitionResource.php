<?php

namespace App\Resources\Template;

use App\Models\Template\TemplateDefinition;
use App\Resources\Agent\AgentThreadResource;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;
use Newms87\Danx\Resources\StoredFileResource;

class TemplateDefinitionResource extends ActionResource
{
    public static function data(TemplateDefinition $templateDefinition): array
    {
        return [
            'id'            => $templateDefinition->id,
            'name'          => $templateDefinition->name,
            'description'   => $templateDefinition->description,
            'type'          => $templateDefinition->type,
            'category'      => $templateDefinition->category,
            'is_active'     => $templateDefinition->is_active,
            'metadata'      => $templateDefinition->metadata,
            'template_url'  => $templateDefinition->getTemplateUrl(),
            'google_doc_id' => $templateDefinition->extractGoogleDocId(),
            'created_at'    => $templateDefinition->created_at,
            'updated_at'    => $templateDefinition->updated_at,

            // HTML template fields (lazy loaded)
            'html_content' => fn() => $templateDefinition->html_content,
            'css_content'  => fn() => $templateDefinition->css_content,

            // Relationships (loaded conditionally)
            'stored_file'         => fn($fields) => $templateDefinition->storedFile ? StoredFileResource::make($templateDefinition->storedFile, $fields) : null,
            'preview_stored_file' => fn($fields) => $templateDefinition->previewStoredFile ? StoredFileResource::make($templateDefinition->previewStoredFile, $fields) : null,
            'user'                => fn($fields) => $templateDefinition->user ? [
                'id'    => $templateDefinition->user->id,
                'name'  => $templateDefinition->user->name,
                'email' => $templateDefinition->user->email,
            ] : null,
            'template_variables'    => fn($fields) => TemplateVariableResource::collection($templateDefinition->templateVariables, $fields),
            'history'               => fn($fields) => TemplateDefinitionHistoryResource::collection($templateDefinition->history, $fields),
            'collaboration_threads' => fn($fields) => AgentThreadResource::collection($templateDefinition->collaborationThreads, $fields),
        ];
    }

    #[\Override]
    public static function details(Model $model, ?array $includeFields = null): array
    {
        return static::make($model, $includeFields ?? [
            'stored_file'           => true,
            'preview_stored_file'   => true,
            'user'                  => true,
            'template_variables'    => true,
            'history'               => true,
            'collaboration_threads' => ['messages' => true],
            'html_content'          => true,
            'css_content'           => true,
        ]);
    }
}
