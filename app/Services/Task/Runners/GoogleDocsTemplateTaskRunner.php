<?php

namespace App\Services\Task\Runners;

use App\Api\GoogleDocs\GoogleDocsApi;
use App\Models\Demand\DemandTemplate;
use App\Models\Task\Artifact;
use App\Models\TeamObject\TeamObject;
use App\Services\ContentSearch\ContentSearchRequest;
use App\Services\ContentSearch\ContentSearchService;
use App\Services\Demand\TemplateVariableResolutionService;
use Exception;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Models\Utilities\StoredFile;

class GoogleDocsTemplateTaskRunner extends AgentThreadTaskRunner
{
    const string RUNNER_NAME = 'Google Docs Template';

    public function run(): void
    {
        // Step 1: Find the Google Doc template stored file
        $storedFile = $this->findGoogleDocStoredFile();
        if (!$storedFile) {
            throw new Exception("Template could not be resolved. No Google Docs template found in artifacts, text content, or directives.");
        }

        // Step 2: Extract Google Doc ID from the stored file URL
        $googleDocFileId = $this->extractGoogleDocIdFromStoredFile($storedFile);
        if (!$googleDocFileId) {
            throw new Exception("Could not extract Google Doc ID from StoredFile URL: {$storedFile->url}");
        }

        // Step 3: Find DemandTemplate by stored_file_id
        $template = $this->findDemandTemplate($storedFile);

        // Step 4: Load template variables from database
        $templateVariables = $template->templateVariables;

        static::log('Template variables loaded from database', [
            'template_id' => $template->id,
            'variables_count' => $templateVariables->count(),
        ]);

        // Step 5: Find TeamObject from input artifacts (if present)
        $teamObject = $this->findTeamObjectFromArtifacts($this->taskProcess->inputArtifacts);

        // Step 6: Resolve variables using TemplateVariableResolutionService
        $resolution = app(TemplateVariableResolutionService::class)->resolveVariables(
            $templateVariables,
            $this->taskProcess->inputArtifacts,
            $teamObject,
            $this->taskDefinition->team_id
        );

        static::log('Variables resolved', [
            'values_count' => count($resolution['values']),
            'title' => $resolution['title'],
        ]);

        // Step 7: Create document from template
        $googleDocsApi = app(GoogleDocsApi::class);
        $newDocument = $googleDocsApi->createDocumentFromTemplate(
            $googleDocFileId,
            $resolution['values'],
            $resolution['title']
        );

        static::log('Document created', [
            'document_id' => $newDocument['document_id'],
            'url' => $newDocument['url'],
        ]);

        // Step 8: Create output artifact
        $artifact = $this->createOutputArtifact($newDocument, $resolution);
        $this->complete([$artifact]);
    }

    /**
     * Find the Google Doc template stored file from artifacts or text content
     */
    protected function findGoogleDocStoredFile(): StoredFile
    {
        $request = ContentSearchRequest::create()
            ->searchArtifacts($this->taskProcess->inputArtifacts)
            ->withFieldPath('template_stored_file_id')
            ->withRegexPattern("/[a-zA-Z0-9_-]{25,}/")
            ->withTaskDefinition($this->taskDefinition)
            ->withNaturalLanguageQuery("Your job is to find a Google Doc ID that would be used in a URL or in the API to identify a google doc. An example URL is https://docs.google.com/document/d/1eXaMpLeGOOglEDocUrlEhhhh_qJNDkElfmxEKMMDMKEddmiAa. Try to find the real google doc ID in the context given. If you no ID is found then DO NOT return a google doc ID.");

        $result = app(ContentSearchService::class)->search($request);

        if (!$result->isFound()) {
            throw new Exception("No Google Doc template found in artifacts or text content.");
        }

        $storedFileId = $result->getValue();

        $storedFile = StoredFile::find($storedFileId);
        if (!$storedFile) {
            throw new Exception("StoredFile with ID $storedFileId not found.");
        }

        return $storedFile;
    }

    /**
     * Find DemandTemplate by stored_file_id
     */
    protected function findDemandTemplate(StoredFile $storedFile): DemandTemplate
    {
        $template = DemandTemplate::with('templateVariables')
            ->where('stored_file_id', $storedFile->id)
            ->first();

        if (!$template) {
            throw new ValidationError(
                "No DemandTemplate found for StoredFile ID {$storedFile->id}. Please create a template configuration first.",
                404
            );
        }

        return $template;
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
            return (int) $data['team_object_id'];
        }

        // Nested object reference
        if (isset($data['team_object']['id'])) {
            return (int) $data['team_object']['id'];
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
     * Extract Google Doc ID from StoredFile URL
     */
    protected function extractGoogleDocIdFromStoredFile(StoredFile $storedFile): ?string
    {
        // Extract Google Doc ID from URL
        if (preg_match('/\/document\/d\/([a-zA-Z0-9_-]+)/', $storedFile->url, $matches)) {
            return $matches[1];
        }

        return null;
    }


    /**
     * Create output artifact with Google Docs information
     */
    protected function createOutputArtifact(array $newDocument, array $resolution): Artifact
    {
        $artifact = Artifact::create([
            'team_id'      => $this->taskDefinition->team_id,
            'name'         => 'Generated Google Doc: ' . $newDocument['title'],
            'text_content' => "Successfully created Google Docs document from template.\n\nDocument Title: {$newDocument['title']}\nDocument URL: {$newDocument['url']}\n\nVariable Mapping:\n" . json_encode($resolution['values'], JSON_PRETTY_PRINT),
            'meta'         => [
                'google_doc_url'   => $newDocument['url'],
                'google_doc_id'    => $newDocument['document_id'],
                'document_title'   => $newDocument['title'],
                'created_at'       => $newDocument['created_at'],
                'variable_mapping' => $resolution['values'],
                'resolved_title'   => $resolution['title'] ?? null,
            ],
            'json_content' => [
                'document'   => $newDocument,
                'resolution' => $resolution,
            ],
        ]);

        // Create and attach StoredFile for the generated Google Doc
        $storedFile = $this->createGoogleDocsStoredFile($newDocument);
        $artifact->storedFiles()->attach($storedFile->id);

        return $artifact;
    }

    /**
     * Create StoredFile for Google Docs output
     */
    protected function createGoogleDocsStoredFile(array $newDocument): StoredFile
    {
        $storedFile = new StoredFile([
            'disk'     => 'external',
            'filepath' => $newDocument['url'],
            'filename' => $newDocument['title'] . '.gdoc',
            'mime'     => 'application/vnd.google-apps.document',
            'size'     => 0,
            'url'      => $newDocument['url'],
            'meta'     => [
                'type'        => 'google_docs',
                'document_id' => $newDocument['document_id'],
                'created_at'  => $newDocument['created_at'],
            ],
        ]);

        // Set team_id and user_id separately since they're not fillable
        $storedFile->team_id = $this->taskDefinition->team_id;
        $storedFile->user_id = user()?->id;
        $storedFile->save();

        return $storedFile;
    }
}
