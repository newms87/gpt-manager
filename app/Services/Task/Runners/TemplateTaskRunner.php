<?php

namespace App\Services\Task\Runners;

use App\Models\Task\Artifact;
use App\Models\TeamObject\TeamObject;
use App\Models\Template\TemplateDefinition;
use App\Services\ContentSearch\ContentSearchRequest;
use App\Services\ContentSearch\ContentSearchService;
use App\Services\Template\TemplateRenderingService;
use App\Services\Template\TemplateRenderResult;
use Exception;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Models\Utilities\StoredFile;

/**
 * Task runner for rendering templates (Google Docs and HTML).
 * Thin orchestration layer that delegates to TemplateRenderingService.
 */
class TemplateTaskRunner extends AgentThreadTaskRunner
{
    const string RUNNER_NAME = 'Template';

    public function run(): void
    {
        // Step 1: Find the template definition
        $template = $this->findTemplateDefinition();

        static::logDebug('Template definition found', [
            'template_id'   => $template->id,
            'template_name' => $template->name,
            'template_type' => $template->type,
        ]);

        // Step 2: Find TeamObject from input artifacts (if present)
        $teamObject = $this->findTeamObjectFromArtifacts($this->taskProcess->inputArtifacts);

        // Step 3: Render the template
        $result = app(TemplateRenderingService::class)->render(
            $template,
            $this->taskProcess->inputArtifacts,
            $teamObject,
            $this->taskDefinition->team_id
        );

        static::logDebug('Template rendered', [
            'type'  => $result->type,
            'title' => $result->title,
        ]);

        // Step 4: Create output artifact based on result type
        $artifact = $this->createOutputArtifact($result);
        $this->complete([$artifact]);
    }

    /**
     * Find the template definition from artifacts, content search, or directives
     */
    protected function findTemplateDefinition(): TemplateDefinition
    {
        // First try to find via stored file ID (Google Docs templates)
        $storedFile = $this->findTemplateStoredFile();
        if ($storedFile) {
            $template = TemplateDefinition::with('templateVariables')
                ->where('stored_file_id', $storedFile->id)
                ->first();

            if ($template) {
                return $template;
            }
        }

        // Try to find template by ID directly in artifacts
        $templateId = $this->findTemplateIdFromArtifacts();
        if ($templateId) {
            $template = TemplateDefinition::with('templateVariables')->find($templateId);
            if ($template) {
                return $template;
            }
        }

        throw new ValidationError(
            'No template definition found. Provide template_stored_file_id or template_definition_id in artifact content.',
            404
        );
    }

    /**
     * Find template stored file from artifacts using ContentSearchService
     */
    protected function findTemplateStoredFile(): ?StoredFile
    {
        $request = ContentSearchRequest::create()
            ->searchArtifacts($this->taskProcess->inputArtifacts)
            ->withFieldPath('template_stored_file_id')
            ->withRegexPattern('/[a-zA-Z0-9_-]{25,}/')
            ->withTaskDefinition($this->taskDefinition)
            ->withNaturalLanguageQuery('Your job is to find a Google Doc ID that would be used in a URL or in the API to identify a google doc. An example URL is https://docs.google.com/document/d/1eXaMpLeGOOglEDocUrlEhhhh_qJNDkElfmxEKMMDMKEddmiAa. Try to find the real google doc ID in the context given. If you no ID is found then DO NOT return a google doc ID.');

        $result = app(ContentSearchService::class)->search($request);

        if (!$result->isFound()) {
            return null;
        }

        $storedFileId = $result->getValue();

        return StoredFile::find($storedFileId);
    }

    /**
     * Find template definition ID directly from artifacts
     */
    protected function findTemplateIdFromArtifacts(): ?int
    {
        foreach ($this->taskProcess->inputArtifacts as $artifact) {
            // Search in json_content
            if ($artifact->json_content) {
                $templateId = $artifact->json_content['template_definition_id']
                    ?? $artifact->json_content['template_id']
                    ?? null;
                if ($templateId) {
                    return (int)$templateId;
                }
            }

            // Search in meta
            if ($artifact->meta) {
                $templateId = $artifact->meta['template_definition_id']
                    ?? $artifact->meta['template_id']
                    ?? null;
                if ($templateId) {
                    return (int)$templateId;
                }
            }
        }

        return null;
    }

    /**
     * Find TeamObject from input artifacts
     */
    protected function findTeamObjectFromArtifacts($artifacts): ?TeamObject
    {
        foreach ($artifacts as $artifact) {
            // Search in json_content
            if ($artifact->json_content) {
                $teamObjectId = $this->extractTeamObjectIdFromData($artifact->json_content);
                if ($teamObjectId) {
                    return TeamObject::find($teamObjectId);
                }
            }

            // Search in meta
            if ($artifact->meta) {
                $teamObjectId = $this->extractTeamObjectIdFromData($artifact->meta);
                if ($teamObjectId) {
                    return TeamObject::find($teamObjectId);
                }
            }
        }

        return null;
    }

    /**
     * Extract TeamObject ID from nested array data
     */
    protected function extractTeamObjectIdFromData(array $data): ?int
    {
        // Direct reference
        if (isset($data['team_object_id'])) {
            return (int)$data['team_object_id'];
        }

        // Nested object reference
        if (isset($data['team_object']['id'])) {
            return (int)$data['team_object']['id'];
        }

        // Search recursively
        foreach ($data as $value) {
            if (is_array($value)) {
                $id = $this->extractTeamObjectIdFromData($value);
                if ($id) {
                    return $id;
                }
            }
        }

        return null;
    }

    /**
     * Create output artifact based on render result type
     */
    protected function createOutputArtifact(TemplateRenderResult $result): Artifact
    {
        return match ($result->type) {
            'google_docs' => $this->createGoogleDocsOutputArtifact($result),
            'html'        => $this->createHtmlOutputArtifact($result),
            default       => throw new Exception("Unsupported result type: {$result->type}"),
        };
    }

    /**
     * Create output artifact for Google Docs result
     */
    protected function createGoogleDocsOutputArtifact(TemplateRenderResult $result): Artifact
    {
        $artifact = Artifact::create([
            'team_id'      => $this->taskDefinition->team_id,
            'name'         => 'Generated Google Doc: ' . $result->title,
            'text_content' => "Successfully created Google Docs document from template.\n\nDocument Title: {$result->title}\nDocument URL: {$result->url}\n\nVariable Mapping:\n" . json_encode($result->values, JSON_PRETTY_PRINT),
            'meta'         => [
                'google_doc_url'   => $result->url,
                'google_doc_id'    => $result->documentId,
                'document_title'   => $result->title,
                'variable_mapping' => $result->values,
            ],
            'json_content' => [
                'document' => [
                    'url'         => $result->url,
                    'document_id' => $result->documentId,
                    'title'       => $result->title,
                ],
                'resolution' => [
                    'values' => $result->values,
                    'title'  => $result->title,
                ],
            ],
        ]);

        // Attach StoredFile to the artifact
        if ($result->storedFile) {
            $artifact->storedFiles()->attach($result->storedFile->id);
        }

        return $artifact;
    }

    /**
     * Create output artifact for HTML result
     */
    protected function createHtmlOutputArtifact(TemplateRenderResult $result): Artifact
    {
        return Artifact::create([
            'team_id'      => $this->taskDefinition->team_id,
            'name'         => 'Rendered HTML: ' . $result->title,
            'text_content' => $result->html,
            'meta'         => [
                'type'             => 'rendered_html',
                'document_title'   => $result->title,
                'variable_mapping' => $result->values,
                'has_css'          => !empty($result->css),
            ],
            'json_content' => [
                'html'       => $result->html,
                'css'        => $result->css,
                'resolution' => [
                    'values' => $result->values,
                    'title'  => $result->title,
                ],
            ],
        ]);
    }
}
