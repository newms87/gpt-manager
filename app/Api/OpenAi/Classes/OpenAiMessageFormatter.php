<?php

namespace App\Api\OpenAi\Classes;

use App\AiTools\SummarizerAiTool;
use App\Api\AgentApiContracts\AgentMessageFormatterContract;
use App\Models\Agent\Message;
use Exception;
use Illuminate\Support\Facades\Log;
use Newms87\Danx\Services\TranscodeFileService;

class OpenAiMessageFormatter implements AgentMessageFormatterContract
{
    public function rawMessage(string $role, string $content, array $data = []): array
    {
        return [
                'role'    => $role,
                'content' => $content,
            ] + ($data ?? []);
    }

    public function message(Message $message): array
    {
        // If summary is set, use that instead of the original content of the message (this is to save on tokens and used by the Summarizer AI Tool)
        $content = $message->summary ?: $message->content;

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

        $files = $message->storedFiles()->get();

        // Add Image URLs to the content
        if ($files->isNotEmpty()) {
            $urls      = $this->getFileUrls($files);
            $pagedUrls = SummarizerAiTool::pageItems($message, $urls);

            Log::debug("$message appending " . count($pagedUrls) . " / $message->summarizer_total files");
            $content[] = ['type' => 'text', 'text' => SummarizerAiTool::enabledMessage($message)];

            foreach($pagedUrls as $url) {
                $content[] = [
                    'type'      => 'image_url',
                    'image_url' => ['url' => $url],
                ];
            }
        }

        return $this->rawMessage($message->role, $content, $message->data);
    }

    public function getFileUrls($files): array
    {
        $urls = [];

        foreach($files as $file) {
            if ($file->isImage()) {
                $urls[] = $file->url;
            } elseif ($file->isPdf()) {
                $transcodes = $file->transcodes()->where('transcode_name', TranscodeFileService::TRANSCODE_PDF_TO_IMAGES)->get();

                foreach($transcodes as $transcode) {
                    $urls[] = $transcode->url;
                }
            } else {
                throw new Exception('Only images and PDFs are supported for now.');
            }
        }

        return $urls;
    }
}
