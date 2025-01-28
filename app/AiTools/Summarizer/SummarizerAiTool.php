<?php

namespace App\AiTools\Summarizer;

use App\AiTools\AiToolAbstract;
use App\AiTools\AiToolContract;
use App\AiTools\AiToolResponse;
use App\Models\Agent\AgentThreadMessage;
use BadFunctionCallException;
use Illuminate\Support\Facades\Log;
use Newms87\Danx\Helpers\StringHelper;

class SummarizerAiTool extends AiToolAbstract implements AiToolContract
{
    public static string $name = 'summarizer';

    public static function enabledMessage(AgentThreadMessage $message): string
    {
        return "Summarizer Tool Enabled: MID-$message->id -- $message->summarizer_offset / $message->summarizer_total";
    }

    public static function pageItems(AgentThreadMessage $message, array $items): array
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
        $message         = AgentThreadMessage::find($parsedMessageId);

        if (!$message) {
            throw new BadFunctionCallException("AgentThreadMessage not found: $messageId");
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
