<?php

namespace App\Api\OpenAi\Classes;

use App\Api\AgentApiContracts\AgentMessageFormatterContract;
use App\Traits\HasDebugLogging;
use Exception;
use Newms87\Danx\Models\Utilities\StoredFile;
use Newms87\Danx\Services\TranscodeFileService;

class OpenAiMessageFormatter implements AgentMessageFormatterContract
{
    use HasDebugLogging;

    public function acceptsJsonSchema(): bool
    {
        return true;
    }

    public function messageList(array $messages): array
    {
        return $messages;
    }

    public function rawMessage(string $role, string|array $content, ?array $data = null): array
    {
        return [
            'role'    => $role,
            'content' => $content,
        ] + ($data ?? []);
    }

    /**
     * Convert raw messages from AgentThreadService to Responses API input format
     */
    public function convertRawMessagesToResponsesApiInput(array $rawMessages): array|string
    {
        $input = [];

        foreach ($rawMessages as $messageData) {
            if (!isset($messageData['role']) || !isset($messageData['content'])) {
                continue;
            }

            $content = [];

            // Add text content with correct type based on role
            if (!empty($messageData['content'])) {
                $textType  = $messageData['role'] === 'assistant' ? 'output_text' : 'input_text';
                $content[] = [
                    'type' => $textType,
                    'text' => $messageData['content'],
                ];
            }

            // Add file content (images) with proper processing
            if (!empty($messageData['files'])) {
                $imageFiles   = $this->getImageFiles($messageData['files']);
                $filesContent = $this->formatFilesContent($imageFiles);
                $content      = array_merge($content, $filesContent);
            }

            // Add citation instructions if needed
            if (!empty($messageData['should_cite'])) {
                $content[] = [
                    'type' => 'input_text',
                    'text' => "\n\nPlease cite this message in your response using message ID: {$messageData['id']}",
                ];
            }

            $input[] = [
                'role'    => $messageData['role'],
                'content' => $content,
            ];
        }

        // If only one user message with simple text content, return as string
        if (count($input)                   === 1      &&
            $input[0]['role']               === 'user' &&
            count($input[0]['content'])     === 1      &&
            $input[0]['content'][0]['type'] === 'input_text') {
            return $input[0]['content'][0]['text'];
        }

        return $input;
    }

    /**
     * Get Responses API format for image files
     *
     * @param  StoredFile[]|array  $storedFiles
     */
    protected function formatFilesContent(array $storedFiles, $detail = 'high'): array
    {
        static::logDebug("\tappending " . count($storedFiles) . ' files');

        $filesContent = [];

        foreach ($storedFiles as $storedFile) {
            $url = $storedFile instanceof StoredFile ? $storedFile->url : $storedFile->url ?? $storedFile['url'] ?? null;

            if ($url) {
                $filesContent[] = [
                    'type'      => 'input_image',
                    'image_url' => $url,
                ];
            }
        }

        return $filesContent;
    }

    /**
     * Get the file name/url pairs for all images and split PDFs into individual images
     *
     * @param  StoredFile[]  $files
     * @return StoredFile[]
     *
     * @throws Exception
     */
    public function getImageFiles($files): array
    {
        $imageFiles = [];

        foreach ($files as $file) {
            if ($file->isImage()) {
                $imageFiles[] = $file;
            } elseif ($file->isPdf()) {
                /** @var StoredFile[] $transcodes */
                $transcodes = $file->transcodes()->where('transcode_name', TranscodeFileService::TRANSCODE_PDF_TO_IMAGES)->get();

                foreach ($transcodes as $transcode) {
                    $imageFiles[] = $transcode;
                }
            } else {
                throw new Exception('Only images and PDFs are supported for now.');
            }
        }

        return $imageFiles;
    }
}
