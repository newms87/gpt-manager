<?php

namespace App\Services\DemandTemplate;

use App\Api\GoogleDocs\GoogleDocsApi;
use App\Models\Demand\DemandTemplate;
use App\Services\GoogleDocs\GoogleDocsFileService;
use Exception;
use Newms87\Danx\Exceptions\ValidationError;

class DemandTemplateService
{
    public function __construct(
        private readonly GoogleDocsFileService $googleDocsFileService,
        private readonly GoogleDocsApi         $googleDocsApi
    )
    {
    }

    /**
     * Create a new demand template
     */
    public function createTemplate(array $data): DemandTemplate
    {
        $data['team_id'] = team()->id;
        $data['user_id'] = $data['user_id'] ?? user()->id;

        // Handle Google Docs template URL if provided
        if (isset($data['template_url']) && !empty($data['template_url'])) {
            $storedFileId           = $this->googleDocsFileService->createFromUrl($data['template_url'], $data['name']);
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
            $storedFileId           = $this->googleDocsFileService->createFromUrl($data['template_url'], $name);
            $data['stored_file_id'] = $storedFileId;
        }

        unset($data['template_url']);

        $template->fill($data);
        $template->validate();
        $template->save();

        // Sync template variables to StoredFile if they were updated
        if (isset($data['template_variables'])) {
            $this->syncVariablesToStoredFile($template);
        }

        return $template;
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

    /**
     * Fetch template variables from Google Docs and merge with existing variables
     */
    public function fetchTemplateVariables(DemandTemplate $template): DemandTemplate
    {
        $this->validateOwnership($template);

        $googleDocId = $template->extractGoogleDocId();
        if (!$googleDocId) {
            throw new ValidationError('Template does not have a valid Google Docs URL', 400);
        }

        // Get new variables from Google Docs (returns array of variable names)
        $newVariableNames = $this->googleDocsApi->extractTemplateVariables($googleDocId);

        // Get existing template variables (associative array of variable => description)
        $existingVariables = $template->template_variables ?? [];

        // Merge: keep existing variables with their descriptions, add new ones with empty descriptions
        $mergedVariables = $existingVariables;
        foreach($newVariableNames as $variableName) {
            if (!isset($mergedVariables[$variableName])) {
                $mergedVariables[$variableName] = '';
            }
        }

        $template->template_variables = $mergedVariables;
        $template->save();

        // Sync to StoredFile meta for TaskRunner consumption
        $this->syncVariablesToStoredFile($template);

        return $template->fresh();
    }

    /**
     * Sync template variables to StoredFile meta field
     */
    public function syncVariablesToStoredFile(DemandTemplate $template): void
    {
        if (!$template->storedFile || !$template->template_variables) {
            throw new Exception('Template does not have a stored file or template variables are empty: ' . $template->storedFile?->url . ' - ' . json_encode($template->template_variables), 400);
        }

        $storedFile                 = $template->storedFile;
        $meta                       = $storedFile->meta ?? [];
        $meta['template_variables'] = $template->template_variables;

        $storedFile->meta = $meta;
        $storedFile->save();
    }
}
