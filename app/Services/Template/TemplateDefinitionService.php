<?php

namespace App\Services\Template;

use App\Api\GoogleDocs\GoogleDocsApi;
use App\Jobs\TemplateCollaborationJob;
use App\Models\Agent\AgentThread;
use App\Models\Agent\AgentThreadMessage;
use App\Models\Template\TemplateDefinition;
use App\Models\Template\TemplateDefinitionHistory;
use App\Services\Demand\TemplateVariableService;
use App\Services\GoogleDocs\GoogleDocsFileService;
use Illuminate\Database\Eloquent\Collection;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Helpers\ModelHelper;
use Newms87\Danx\Models\Utilities\StoredFile;

/**
 * Service for managing template definitions.
 *
 * Handles CRUD operations for templates, Google Docs template processing,
 * and HTML template collaboration via LLM.
 */
class TemplateDefinitionService
{
    /**
     * Create a new template definition.
     */
    public function createTemplate(array $data): TemplateDefinition
    {
        $data['team_id'] = team()->id;
        $data['user_id'] = $data['user_id'] ?? user()->id;

        // Default type to google_docs if not specified
        $data['type'] ??= TemplateDefinition::TYPE_GOOGLE_DOCS;

        // Handle Google Docs template URL if provided
        if (isset($data['template_url']) && !empty($data['template_url'])) {
            $storedFileId           = app(GoogleDocsFileService::class)->createFromUrl($data['template_url'], $data['name']);
            $data['stored_file_id'] = $storedFileId;
        }

        unset($data['template_url']);

        $template       = new TemplateDefinition($data);
        $template->name = ModelHelper::getNextModelName($template, 'name', ['team_id' => team()->id]);
        $template->validate();
        $template->save();

        return $template;
    }

    /**
     * Update an existing template definition.
     */
    public function updateTemplate(TemplateDefinition $template, array $data): TemplateDefinition
    {
        $this->validateOwnership($template);

        // Handle template_url by creating StoredFile if provided
        if (isset($data['template_url']) && !empty($data['template_url'])) {
            $name                   = $data['name'] ?? $template->name;
            $storedFileId           = app(GoogleDocsFileService::class)->createFromUrl($data['template_url'], $name);
            $data['stored_file_id'] = $storedFileId;
        }

        unset($data['template_url']);

        $template->fill($data);
        $template->validate();
        $template->save();

        return $template;
    }

    /**
     * Fetch template variables from Google Docs template.
     */
    public function fetchTemplateVariables(TemplateDefinition $template): Collection
    {
        $this->validateOwnership($template);

        if (!$template->storedFile) {
            throw new ValidationError('Template definition does not have a stored file', 400);
        }

        // Extract document ID from stored file URL/filepath
        $documentId = app(GoogleDocsFileService::class)->extractDocumentId($template->storedFile->filepath);
        if (!$documentId) {
            throw new ValidationError('Stored file does not have a valid Google Docs document ID', 400);
        }

        // Extract variable names from Google Docs template
        $variableNames = app(GoogleDocsApi::class)->extractTemplateVariables($documentId);

        // Use TemplateVariableService to sync variables
        return app(TemplateVariableService::class)
            ->syncVariablesFromGoogleDoc($template, $variableNames);
    }

    /**
     * Start a new collaboration thread for HTML template generation.
     *
     * Users can start collaboration with:
     * - Just files (upload PDF/images)
     * - Just a prompt (describe what they want)
     * - Both files and a prompt
     *
     * @param  array<int>  $fileIds  IDs of StoredFiles to use as source documents (optional)
     * @param  string|null  $prompt  Initial prompt describing what the user wants (optional)
     */
    public function startCollaboration(TemplateDefinition $template, array $fileIds = [], ?string $prompt = null): AgentThread
    {
        $this->validateOwnership($template);

        // Validate that at least one input is provided (files or prompt)
        if (empty($fileIds) && empty($prompt)) {
            throw new ValidationError('Please provide source files, a prompt, or both to start collaboration');
        }

        // Load the source files if any were provided
        $sourceFiles = collect();
        if (!empty($fileIds)) {
            $sourceFiles = StoredFile::whereIn('id', $fileIds)->get();

            if ($sourceFiles->isEmpty()) {
                throw new ValidationError('No valid source files found for the provided IDs');
            }
        }

        return app(TemplateCollaborationService::class)->startCollaboration(
            $template,
            $sourceFiles,
            team()->id,
            $prompt
        );
    }

    /**
     * Send a message to an existing collaboration thread.
     *
     * Dispatches the message processing async and returns immediately.
     *
     * @return array{status: string, job_dispatch_id: int|null}
     */
    public function sendMessage(AgentThread $thread, string $message, ?int $fileId = null): array
    {
        $this->validateThreadOwnership($thread);

        $job = new TemplateCollaborationJob($thread, $message, $fileId);
        $job->dispatch();

        $jobDispatch = $job->getJobDispatch();

        // Attach job dispatch to template for Jobs tab tracking
        if ($jobDispatch && $thread->collaboratable instanceof TemplateDefinition) {
            $thread->collaboratable->jobDispatches()->attach($jobDispatch->id);
            $thread->collaboratable->updateRelationCounter('jobDispatches');
        }

        return [
            'status'          => 'queued',
            'job_dispatch_id' => $jobDispatch?->id,
        ];
    }

    /**
     * Upload a screenshot response to a message.
     */
    public function uploadScreenshot(AgentThreadMessage $message, int $fileId): void
    {
        $screenshot = StoredFile::find($fileId);

        if (!$screenshot) {
            throw new ValidationError('Screenshot file not found');
        }

        app(TemplateBuildingService::class)->handleScreenshotResponse($message, $screenshot);
    }

    /**
     * Restore a template to a previous version from history.
     */
    public function restoreVersion(TemplateDefinitionHistory $history): TemplateDefinition
    {
        $template = $history->templateDefinition;

        $this->validateOwnership($template);

        $history->restore();

        return $template->fresh();
    }

    /**
     * Validate that the current team owns the template.
     */
    public function validateOwnership(TemplateDefinition $template): void
    {
        $currentTeam = team();
        if (!$currentTeam || $template->team_id !== $currentTeam->id) {
            throw new ValidationError('You do not have permission to access this template definition', 403);
        }
    }

    /**
     * Validate that the current team owns the thread.
     */
    protected function validateThreadOwnership(AgentThread $thread): void
    {
        $currentTeam = team();
        if (!$currentTeam || $thread->team_id !== $currentTeam->id) {
            throw new ValidationError('You do not have permission to access this thread', 403);
        }
    }
}
