<?php

namespace App\Services\DemandTemplate;

use App\Api\GoogleDocs\GoogleDocsApi;
use App\Models\Demand\DemandTemplate;
use App\Services\GoogleDocs\GoogleDocsFileService;
use Illuminate\Database\Eloquent\Collection;
use Newms87\Danx\Exceptions\ValidationError;

class DemandTemplateService
{
    /**
     * Create a new demand template
     */
    public function createTemplate(array $data): DemandTemplate
    {
        $data['team_id'] = team()->id;
        $data['user_id'] = $data['user_id'] ?? user()->id;

        // Handle Google Docs template URL if provided
        if (isset($data['template_url']) && !empty($data['template_url'])) {
            $storedFileId           = app(GoogleDocsFileService::class)->createFromUrl($data['template_url'], $data['name']);
            $data['stored_file_id'] = $storedFileId;
        }

        unset($data['template_url']);

        $template = new DemandTemplate($data);
        $template->validate();
        $template->save();

        return $template;
    }

    /**
     * Update a demand template
     */
    public function updateTemplate(DemandTemplate $template, array $data): DemandTemplate
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
     * Fetch template variables from Google Docs template
     */
    public function fetchTemplateVariables(DemandTemplate $template): Collection
    {
        $this->validateOwnership($template);

        if (!$template->storedFile) {
            throw new ValidationError('Demand template does not have a stored file', 400);
        }

        // Extract document ID from stored file URL/filepath
        $documentId = app(GoogleDocsFileService::class)->extractDocumentId($template->storedFile->filepath);
        if (!$documentId) {
            throw new ValidationError('Stored file does not have a valid Google Docs document ID', 400);
        }

        // Extract variable names from Google Docs template
        $variableNames = app(GoogleDocsApi::class)->extractTemplateVariables($documentId);

        // Use TemplateVariableService to sync variables
        return app(\App\Services\Demand\TemplateVariableService::class)
            ->syncVariablesFromGoogleDoc($template, $variableNames);
    }

    /**
     * Validate that the current team owns the template
     */
    public function validateOwnership(DemandTemplate $template): void
    {
        $currentTeam = team();
        if (!$currentTeam || $template->team_id !== $currentTeam->id) {
            throw new ValidationError('You do not have permission to access this demand template', 403);
        }
    }
}
