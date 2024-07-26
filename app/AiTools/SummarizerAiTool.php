<?php

namespace App\AiTools;

use App\Models\Agent\Message;
use BadFunctionCallException;
use Illuminate\Support\Facades\Log;
use Newms87\Danx\Helpers\StringHelper;

class SummarizerAiTool implements AiToolContract
{
    const string NAME        = 'summarizer';
    const string DESCRIPTION = 'Summarize the response for the request in JSON (or text if not collecting data) based on the currently available information from the provided text or files. Only make 1 call to summarize the tool per unique Message ID (ie: M-12345). IMPORTANT: Keep calling the tool until all files have been read or you\'re 100% sure you have all information necessary.';
    const array  PARAMETERS  = [
        'type'       => 'object',
        'properties' => [
            'message_id' => [
                'type'        => 'string',
                'description' => 'The message ID to summarize in format MSG-12345 (given in message when summarization is allowed)',
            ],
            'summary'    => [
                'type'        => 'object',
                'description' => 'The currently collected data as JSON or if the response is text based use {content: ""}, then a summary of the findings form the files / text you are viewing. INCLUDE AS MUCH RELEVANT INFORMATION AS POSSIBLE IN THE SUMMARY!!',
                "properties"  => [
                    "content" => [
                        "type"        => "string",
                        "description" => "The summary of the data collected from the message or files.",
                    ],
                    "data"    => [
                        "type"        => "object",
                        "description" => "The data collected from the message or files.",
                    ],
                ],
            ],
            'next'       => [
                'type'        => 'boolean',
                'description' => 'Continue summarizing the next message or files. Set to true unless you know you have all the information necessary to complete the task.',
            ],
        ],
        'required'   => ['message_id', 'summary', 'next'],
    ];

    public static function enabledMessage(Message $message): string
    {
        return "Summarizer Tool Enabled: MID-$message->id -- $message->summarizer_offset / $message->summarizer_total";
    }

    public static function pageItems(Message $message, array $items): array
    {
        //  Setup the summarizer tool
        $message->summarizer_total = count($items);

        // If summary has been reset, reset the offset
        if (!$message->summary) {
            $message->summarizer_offset = 0;
        }

        // TODO: Implement Summarizer tool settings
        $limit      = 10;
        $count      = 0;
        $pagedItems = [];

        foreach($items as $item) {
            if ($count >= $message->summarizer_offset) {
                $pagedItems[] = $item;
            }
            if (++$count >= $limit) {
                break;
            }
        }

        $message->summarizer_offset += count($pagedItems);
        $message->save();

        return $pagedItems;
    }

    public function execute($params): AiToolResponse
    {
        $messageId = $params['message_id'] ?? null;
        $summary   = $params['summary'] ?? null;
        $next      = $params['next'] ?? null;

        if (!is_string($summary)) {
            $summary = StringHelper::safeJsonEncode($summary);
        }

        // TODO: Implement Summarizer tool settings
        $limit = 10;

        Log::debug("Executing Summarizer AI Tool: $messageId\n\nSummary: " . strlen($summary) . " Bytes\n\nNext: " . ($next ? 'true' : 'false'));

        if (!$messageId || !$summary || !is_bool($next)) {
            throw new BadFunctionCallException("Summarizer requires a message ID, summary, and next boolean");
        }

        $parsedMessageId = str_replace('MID-', '', $messageId);
        $message         = Message::find($parsedMessageId);

        if (!$message) {
            throw new BadFunctionCallException("Message not found: $messageId");
        }

        $to               = $message->summarizer_offset + $limit;
        $message->summary .= "($message->summarizer_offset to $to) $summary\n\n";

        // If next is true, continue summarizing the next message
        if ($next) {
            $message->summarizer_offset = min($message->summarizer_offset + $limit, $message->summarizer_total);
        }

        $message->save();

        $summaryMessage = "Summarizer: MID-$message->id -- $message->summarizer_offset / $message->summarizer_total" . ($message->summarizer_offset >= $message->summarizer_total ? ' (no more pages)' : '');

        return (new AiToolResponse)->addContent($summaryMessage);
    }
}
