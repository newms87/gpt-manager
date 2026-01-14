<?php

namespace App\Resources\Template;

use App\Models\Template\TemplateDefinition;
use App\Resources\Agent\AgentThreadResource;
use App\Resources\Auth\UserResource;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Resources\ActionResource;
use Newms87\Danx\Resources\Job\JobDispatchResource;
use Newms87\Danx\Resources\StoredFileResource;

class TemplateDefinitionResource extends ActionResource
{
    public static function data(TemplateDefinition $templateDefinition): array
    {
        return [
            'id'                       => $templateDefinition->id,
            'name'                     => $templateDefinition->name,
            'description'              => $templateDefinition->description,
            'type'                     => $templateDefinition->type,
            'category'                 => $templateDefinition->category,
            'is_active'                => $templateDefinition->is_active,
            'metadata'                 => $templateDefinition->metadata,
            'template_url'             => $templateDefinition->getTemplateUrl(),
            'google_doc_id'            => $templateDefinition->extractGoogleDocId(),
            'created_at'               => $templateDefinition->created_at,
            'updated_at'               => $templateDefinition->updated_at,
            'building_job_dispatch_id' => $templateDefinition->building_job_dispatch_id,
            'pending_build_context'    => $templateDefinition->pending_build_context,

            // Cached counter columns for lightweight counts
            'job_dispatch_count'       => user()?->can('view_jobs_in_ui')
                ? $templateDefinition->job_dispatches_count
                : null,
            'template_variable_count'  => $templateDefinition->template_variables_count,

            // HTML template fields (lazy loaded)
            'html_content'             => fn() => $templateDefinition->html_content,
            'css_content'              => fn() => $templateDefinition->css_content,

            // Relationships (loaded conditionally)
            'building_job_dispatch'    => fn($fields) => JobDispatchResource::make($templateDefinition->buildingJobDispatch, $fields),
            'stored_file'              => fn($fields) => StoredFileResource::make($templateDefinition->storedFile, $fields),
            'preview_stored_file'      => fn($fields) => StoredFileResource::make($templateDefinition->previewStoredFile, $fields),
            'user'                     => fn($fields) => UserResource::make($templateDefinition->user, $fields),
            'template_variables'       => fn($fields) => TemplateVariableResource::collection($templateDefinition->templateVariables, $fields),
            'history'                  => fn($fields) => TemplateDefinitionHistoryResource::collection($templateDefinition->history, $fields),
            'collaboration_threads'    => fn($fields) => AgentThreadResource::collection($templateDefinition->collaborationThreads, $fields),
            'job_dispatches'           => fn($fields) => user()?->can('view_jobs_in_ui')
                ? JobDispatchResource::collection($templateDefinition->jobDispatches()->with('runningAuditRequest')->get(), $fields)
                : null,
        ];
    }

    #[\Override]
    public static function details(Model $model, ?array $includeFields = null): array
    {
        return static::make($model, $includeFields ?? [
            'stored_file'           => true,
            'preview_stored_file'   => true,
            'building_job_dispatch' => true,
            'user'                  => true,
            'template_variables'    => true,
            'history'               => true,
            'collaboration_threads' => ['messages' => true],
            'html_content'          => true,
            'css_content'           => true,
        ]);
    }
}
