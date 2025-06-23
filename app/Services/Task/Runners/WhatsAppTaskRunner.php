<?php

namespace App\Services\Task\Runners;

use App\Models\Task\Artifact;
use App\Models\WhatsApp\WhatsAppConnection;
use App\Services\WhatsApp\WhatsAppService;
use Exception;

class WhatsAppTaskRunner extends BaseTaskRunner
{
    const string RUNNER_NAME = 'WhatsApp';

    public function prepareProcess(): void
    {
        $this->activity("Preparing WhatsApp message processing", 1);
        
        $connectionId = $this->config('connection_id');
        if (!$connectionId) {
            throw new Exception("WhatsApp connection is required");
        }

        $connection = WhatsAppConnection::find($connectionId);
        if (!$connection || !$connection->isConnected()) {
            throw new Exception("WhatsApp connection is not available or not connected");
        }

        $this->taskProcess->name = "WhatsApp: " . $connection->name;
    }

    public function run(): void
    {
        $action = $this->config('action', 'send_message');
        
        match($action) {
            'send_message' => $this->sendMessage(),
            'sync_messages' => $this->syncMessages(),
            'process_messages' => $this->processMessages(),
            default => throw new Exception("Unknown WhatsApp action: {$action}"),
        };
    }

    protected function sendMessage(): void
    {
        $connectionId = $this->config('connection_id');
        $phoneNumber = $this->config('phone_number');
        $messageTemplate = $this->config('message_template');
        
        if (!$phoneNumber) {
            throw new Exception("Phone number is required for sending messages");
        }
        
        if (!$messageTemplate) {
            throw new Exception("Message template is required");
        }

        $connection = WhatsAppConnection::findOrFail($connectionId);
        $whatsAppService = app(WhatsAppService::class);

        $this->activity("Preparing messages", 10);

        $artifacts = [];
        $messageCount = 0;

        foreach ($this->taskProcess->inputArtifacts as $artifact) {
            $this->activity("Processing artifact: {$artifact->name}", 20 + ($messageCount * 60 / max(1, $this->taskProcess->inputArtifacts->count())));
            
            $message = $this->generateMessageFromTemplate($messageTemplate, $artifact);
            
            try {
                $whatsAppMessage = $whatsAppService->sendMessage($connection, $phoneNumber, $message);
                
                $outputArtifact = new Artifact([
                    'name' => "WhatsApp Message to {$phoneNumber}",
                    'text_content' => $message,
                    'json_content' => [
                        'phone_number' => $phoneNumber,
                        'message_id' => $whatsAppMessage->id,
                        'external_id' => $whatsAppMessage->external_id,
                        'status' => $whatsAppMessage->status,
                    ],
                    'task_definition_id' => $this->taskDefinition->id,
                    'task_process_id' => $this->taskProcess->id,
                ]);
                
                $outputArtifact->save();
                $artifacts[] = $outputArtifact;
                
                $messageCount++;
            } catch (Exception $e) {
                $this->activity("Failed to send message: " . $e->getMessage(), 100);
                throw $e;
            }
        }

        $this->activity("Sent {$messageCount} WhatsApp messages successfully", 100);
        $this->complete($artifacts);
    }

    protected function syncMessages(): void
    {
        $connectionId = $this->config('connection_id');
        $connection = WhatsAppConnection::findOrFail($connectionId);
        $whatsAppService = app(WhatsAppService::class);

        $this->activity("Syncing messages from WhatsApp", 50);
        
        try {
            $whatsAppService->syncMessages($connection);
            
            $artifact = new Artifact([
                'name' => "WhatsApp Sync Result",
                'json_content' => [
                    'connection_id' => $connection->id,
                    'connection_name' => $connection->name,
                    'synced_at' => now()->toDateTimeString(),
                    'status' => 'success',
                ],
                'task_definition_id' => $this->taskDefinition->id,
                'task_process_id' => $this->taskProcess->id,
            ]);
            
            $artifact->save();
            
            $this->activity("Messages synced successfully", 100);
            $this->complete([$artifact]);
        } catch (Exception $e) {
            $this->activity("Failed to sync messages: " . $e->getMessage(), 100);
            throw $e;
        }
    }

    protected function processMessages(): void
    {
        $connectionId = $this->config('connection_id');
        $filterPhoneNumber = $this->config('filter_phone_number');
        $includeInbound = $this->config('include_inbound', true);
        $includeOutbound = $this->config('include_outbound', false);
        
        $connection = WhatsAppConnection::findOrFail($connectionId);

        $this->activity("Loading WhatsApp messages", 20);

        $query = $connection->messages()->orderBy('created_at', 'desc');
        
        if ($filterPhoneNumber) {
            $query->where(function($q) use ($filterPhoneNumber) {
                $q->where('from_number', $filterPhoneNumber)
                  ->orWhere('to_number', $filterPhoneNumber);
            });
        }
        
        if (!$includeInbound) {
            $query->where('direction', '!=', 'inbound');
        }
        
        if (!$includeOutbound) {
            $query->where('direction', '!=', 'outbound');
        }

        $messages = $query->limit(100)->get();
        
        $this->activity("Processing {$messages->count()} messages", 50);

        $artifacts = [];
        foreach ($messages as $message) {
            $artifact = new Artifact([
                'name' => "WhatsApp Message: {$message->getFormattedNumber('from')} to {$message->getFormattedNumber('to')}",
                'text_content' => $message->message,
                'json_content' => [
                    'message_id' => $message->id,
                    'external_id' => $message->external_id,
                    'from_number' => $message->from_number,
                    'to_number' => $message->to_number,
                    'direction' => $message->direction,
                    'status' => $message->status,
                    'sent_at' => $message->sent_at?->toDateTimeString(),
                    'media_urls' => $message->media_urls,
                    'metadata' => $message->metadata,
                ],
                'task_definition_id' => $this->taskDefinition->id,
                'task_process_id' => $this->taskProcess->id,
            ]);
            
            $artifact->save();
            $artifacts[] = $artifact;
        }

        $this->activity("Processed {$messages->count()} WhatsApp messages", 100);
        $this->complete($artifacts);
    }

    protected function generateMessageFromTemplate(string $template, Artifact $artifact): string
    {
        $replacements = [
            '{artifact.name}' => $artifact->name,
            '{artifact.text}' => $artifact->text_content ?? '',
            '{artifact.id}' => $artifact->id,
        ];

        if ($artifact->json_content) {
            foreach ($artifact->json_content as $key => $value) {
                if (is_scalar($value)) {
                    $replacements["{artifact.{$key}}"] = $value;
                }
            }
        }

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
}