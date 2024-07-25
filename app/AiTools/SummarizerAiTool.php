<?php

namespace App\AiTools;

use App\Models\Agent\Message;
use BadFunctionCallException;
use Illuminate\Support\Facades\Log;

class SummarizerAiTool implements AiToolContract
{
    const string NAME        = 'summarizer';
    const string DESCRIPTION = 'Summarize a long message text or files and optionally continue scanning additional text or files for summarization.';
    const array  PARAMETERS  = [
        'type'       => 'object',
        'properties' => [
            'message_id' => [
                'type'        => 'string',
                'description' => 'The message ID to summarize in format MSG-12345 (given in message when summarization is allowed)',
            ],
            'summary'    => [
                'type'        => 'string',
                'description' => 'The summarization of the current message of files.',
            ],
            'next'       => [
                'type'        => 'boolean',
                'description' => 'Continue summarizing the next message or files. ONLY set to true when it is necessary to read the next message to complete the task. If the task is to fill data points and you already have all data points, set to false.',
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
        $message->save();

        // TODO: Implement Summarizer tool settings
        $limit      = 10;
        $count      = 0;
        $pagedItems = [];

        foreach($items as $item) {
            if ($count >= $message->summarizer_offset) {
                $pagedItems[] = $item;
            }
            if ($count++ >= $limit) {
                break;
            }
        }

        return $pagedItems;
    }

    public function execute($params): string
    {
        $messageId = $params['message_id'] ?? null;
        $summary   = $params['summary'] ?? null;
        $next      = $params['next'] ?? null;

        // TODO: Implement Summarizer tool settings
        $limit = 10;

        Log::debug("Executing Summarizer AI Tool: $messageId\n\n$summary\n\nNext: " . ($next ? 'true' : 'false'));

        if (!$messageId || !$summary || !is_bool($next)) {
            throw new BadFunctionCallException("Summarizer requires a message ID, summary, and next boolean");
        }

        $parsedMessageId = str_replace('MID-', '', $messageId);
        $message         = Message::find($parsedMessageId);

        if (!$message) {
            throw new BadFunctionCallException("Message not found: $messageId");
        }

        $to               = $message->summarizer_offset + $limit;
        $message->summary .= "($message->summarizer_offset to $to) $summary\n";

        // If next is true, continue summarizing the next message
        if ($next) {
            $message->summarizer_offset = min($message->summarizer_offset + $limit, $message->summarizer_total);
        }

        $message->save();

        return "Summarizer: MID-$message->id -- $message->summarizer_offset / $message->summarizer_total" . ($message->summarizer_offset >= $message->summarizer_total ? ' (no more pages)' : '');
    }
}
