<?php

namespace App\Services\DemandTemplate;

use App\Models\DemandTemplate;
use App\Services\GoogleDocs\GoogleDocsFileService;
use Illuminate\Support\Facades\DB;
use Newms87\Danx\Exceptions\ValidationError;

class DemandTemplateService
{
    public function __construct(
        private readonly GoogleDocsFileService $googleDocsFileService
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

        return DB::transaction(function () use ($data) {
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
        });
    }

    /**
     * Update a demand template
     */
    public function updateTemplate(DemandTemplate $template, array $data): DemandTemplate
    {
        $this->validateOwnership($template);

        return DB::transaction(function () use ($template, $data) {
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

            return $template;
        });
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
