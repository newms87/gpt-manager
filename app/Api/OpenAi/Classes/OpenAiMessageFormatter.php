<?php

namespace App\Api\OpenAi\Classes;

use App\AiTools\AiToolResponse;
use App\Api\AgentApiContracts\AgentMessageFormatterContract;
use App\Models\Agent\Message;
use Exception;
use Illuminate\Support\Facades\Log;
use Newms87\Danx\Models\Utilities\StoredFile;
use Newms87\Danx\Services\TranscodeFileService;

class OpenAiMessageFormatter implements AgentMessageFormatterContract
{
    public function rawMessage(string $role, string|array $content, array $data = null): array
    {
        return [
                'role'    => $role,
                'content' => $content,
            ] + ($data ?? []);
    }

    public function toolResponse(string $toolId, string $toolName, AiToolResponse $response): array
    {
        $messages = [];

        $contentItems = $response->getContentItems();

        foreach($contentItems as $index => $contentItem) {
            if ($index === 0) {
                // The first message needs to be the tool response for the Completion API to respond correctly
                $messages[] = $this->rawMessage(Message::ROLE_TOOL, $contentItem, [
                    'tool_call_id' => $toolId,
                    'name'         => $toolName,
                ]);
            } else {
                // Subsequent messages are user messages
                $messages[] = $this->rawMessage(Message::ROLE_USER, $contentItem);
            }
        }

        // If there are file URLs, add them as a separate message
        if ($response->hasFiles()) {
            $messages[] = $this->filesMessage('', $response->getFiles());
        }

        return $messages;
    }

    public function message(Message $message): array
    {
        Log::debug("Appending $message to messages");

        // If summary is set, use that instead of the original content of the message (this is to save on tokens and used by the Summarizer AI Tool)
        $content = $this->formatContentMessage($message->summary ?: $message->content ?: '');

        $files = $message->storedFiles()->get();

        // Add Image URLs to the content
        if ($files->isNotEmpty()) {
            $imageFiles = $this->getImageFiles($files);

            $content = array_merge($content, $this->formatFilesContent($imageFiles));
        }

        return $this->rawMessage($message->role, $content, $message->data);
    }

    /**
     * Build a message with the content and file URLs
     */
    public function filesMessage(array|string $content, array $fileUrls): array
    {
        $content = $this->formatContentMessage($content);

        $content = array_merge($content, $this->formatFilesContent($fileUrls));

        return $this->rawMessage(Message::ROLE_USER, $content);
    }

    /**
     * Get the Open AI parts for Image URL files for the vision API
     * @param StoredFile[]|array $storedFiles
     */
    protected function formatFilesContent(array $storedFiles, $detail = 'high'): array
    {
        Log::debug("\tappending " . count($storedFiles) . " files");

        $filesContent = [];

        foreach($storedFiles as $storedFile) {
            $filesContent[] = [
                'type'      => 'image_url',
                'image_url' => [
                    'url'    => $storedFile instanceof StoredFile ? $storedFile->url : $storedFile['url'],
                    'detail' => $detail,
                ],
            ];
        }

        return $filesContent;
    }

    /**
     * Return a standard Open AI format for a content text message
     */
    protected function formatContentMessage(string|array $content): array
    {
        // If first and last character of the message is a [ and ] then json decode the message as its an array of message elements (ie: text or image_url)
        // This can happen with tool calls or when the user sends a message with multiple elements
        if (str_starts_with($content, '[') && str_ends_with($content, ']')) {
            $decodedContent = json_decode($content, true);
            if ($decodedContent) {
                $content = $decodedContent;
            }
        }

        // Convert string content to an array of text elements
        if (is_string($content)) {
            $content = [
                [
                    'type' => 'text',
                    'text' => $content,
                ],
            ];
        }

        return $content;
    }

    /**
     * Get the file name/url pairs for all images and split PDFs into individual images
     *
     * @param StoredFile[] $files
     * @return StoredFile[]
     * @throws Exception
     */
    public function getImageFiles($files): array
    {
        $imageFiles = [];

        foreach($files as $file) {
            if ($file->isImage()) {
                $imageFiles[] = $file;
            } elseif ($file->isPdf()) {
                /** @var StoredFile[] $transcodes */
                $transcodes = $file->transcodes()->where('transcode_name', TranscodeFileService::TRANSCODE_PDF_TO_IMAGES)->get();

                foreach($transcodes as $transcode) {
                    $imageFiles[] = $transcode;
                }
            } else {
                throw new Exception('Only images and PDFs are supported for now.');
            }
        }

        return $imageFiles;
    }
}
