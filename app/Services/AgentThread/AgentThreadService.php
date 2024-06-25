<?php

namespace App\Services\AgentThread;

use App\Api\AgentApiContracts\AgentCompletionResponseContract;
use App\Jobs\ExecuteThreadRunJob;
use App\Models\Agent\Message;
use App\Models\Agent\Thread;
use App\Models\Agent\ThreadRun;
use App\Services\Database\DatabaseRecordMapper;
use Exception;
use Illuminate\Support\Facades\Log;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Helpers\LockHelper;
use Newms87\Danx\Services\TranscodeFileService;
use Throwable;

class AgentThreadService
{
    /**
     * Run the thread with the agent by calling the AI model API
     */
    public function run(Thread $thread): ThreadRun
    {
        LockHelper::acquire($thread);

        if ($thread->isRunning()) {
            throw new ValidationError('The thread is already running.');
        }

        $agent = $thread->agent;

        if ($thread->messages()->doesntExist()) {
            throw new ValidationError('You must add messages to the thread before running it.');
        }

        $threadRun = $thread->runs()->create([
            'status'          => ThreadRun::STATUS_RUNNING,
            'temperature'     => $agent->temperature,
            'tools'           => $agent->tools,
            'tool_choice'     => 'auto',
            'response_format' => 'text',
            'seed'            => config('ai.seed'),
            'started_at'      => now(),
        ]);

        // Execute the thread run in a job
        (new ExecuteThreadRunJob($threadRun))->dispatch();

        LockHelper::release($thread);

        return $threadRun;
    }

    /**
     * Execute the thread run to completion
     */
    public function executeThreadRun(ThreadRun $threadRun): void
    {
        try {
            Log::debug("Executing $threadRun");

            $thread = $threadRun->thread;
            $agent  = $thread->agent;

            $options = [
                'temperature'     => $threadRun->temperature,
                'tool_choice'     => $threadRun->tool_choice,
                'response_format' => $threadRun->response_format,
                'seed'            => $threadRun->seed,
            ];

            $tools = $agent->formatTools();

            if ($tools) {
                $options['tools'] = $tools;
            }

            do {
                // Get the messages for the next iteration
                $messages     = $this->getMessagesForApi($thread);
                $messageCount = count($messages);
                Log::debug("$thread running with $messageCount messages for $agent");
                $response = $agent->getModelApi()->complete(
                    $agent->model,
                    $messages,
                    $options
                );

                $this->handleResponse($thread, $threadRun, $response);
            } while(!$response->isFinished());
        } catch(Throwable $throwable) {
            $threadRun->status = ThreadRun::STATUS_FAILED;
            $threadRun->save();
            throw $throwable;
        }
    }

    /**
     * Format the messages to be sent to an AI completion API
     */
    public function getMessagesForApi(Thread $thread): array
    {
        $corePrompt = "The current date and time is " . now()->toDateTimeString() . "\n\n";

        $messages = collect([
            [
                'role'    => Message::ROLE_USER,
                'content' => $corePrompt . $thread->agent->prompt,
            ],
        ]);

        foreach($thread->messages()->get() as $message) {
            $content = $message->content;
            // If first and last character of the message is a [ and ] then json decode the message as its an array of message elements (ie: text or image_url)
            if (str_starts_with($content, '[') && str_ends_with($content, ']')) {
                $content = json_decode($content, true);
            }
            $files = $message->storedFiles()->get();

            // Add Image URLs to the content
            if ($files->isNotEmpty()) {
                if (is_string($content)) {
                    $content = [
                        [
                            'type' => 'text',
                            'text' => $content,
                        ],
                    ];
                }

                foreach($files as $file) {
                    if ($file->isImage()) {
                        $content[] = [
                            'type'      => 'image_url',
                            'image_url' => ['url' => $file->url],
                        ];
                    } elseif ($file->isPdf()) {
                        $transcodes = $file->transcodes()->where('transcode_name', TranscodeFileService::TRANSCODE_PDF_TO_IMAGES)->get();

                        foreach($transcodes as $transcode) {
                            $content[] = [
                                'type'      => 'image_url',
                                'image_url' => ['url' => $transcode->url],
                            ];
                            Log::debug("$message appending transcoded file $transcode->url");
                        }
                    } else {
                        throw new Exception('Only images are supported for now.');
                    }
                }
            }

            $messages->push([
                    'role'    => $message->role,
                    'content' => $content,
                ] + ($message->data ?? []));
        }

        return $messages->toArray();
    }


    /**
     * Handle the response from the AI model
     */
    public function handleResponse(Thread $thread, ThreadRun $threadRun, AgentCompletionResponseContract $response): void
    {
        if ($response->isToolCall()) {
            $thread->messages()->create([
                'role'    => Message::ROLE_ASSISTANT,
                'content' => $response->getContent(),
                'data'    => $response->getDataFields(),
            ]);

            foreach($response->getToolCallerFunctions() as $toolCallerFunction) {
                $content = $toolCallerFunction->call();
                $thread->messages()->create([
                    'role'    => Message::ROLE_TOOL,
                    'content' => is_string($content) ? $content : json_encode($content),
                    'data'    => [
                        'tool_call_id' => $toolCallerFunction->getId(),
                        'name'         => $toolCallerFunction->getName(),
                    ],
                ]);
            }
            $threadRun->update(['refreshed_at' => now()]);
        } elseif ($response->isFinished()) {
            $lastMessage = $thread->messages()->create([
                'role'    => Message::ROLE_ASSISTANT,
                'content' => $response->getContent(),
            ]);;

            $threadRun->update([
                'status'          => ThreadRun::STATUS_COMPLETED,
                'completed_at'    => now(),
                'input_tokens'    => $response->inputTokens(),
                'output_tokens'   => $response->outputTokens(),
                'last_message_id' => $lastMessage->id,
            ]);

            if ($lastMessage->content) {
                $jsonData       = json_decode(AgentThreadService::cleanContent($lastMessage->content), true);
                $databaseWrites = $jsonData['write_database'] ?? [];
                if ($databaseWrites) {
                    if (team()->schema_file) {
                        $file = app_path(team()->schema_file);

                        try {
                            (new DatabaseRecordMapper)
                                ->setSchema(team()->namespace, $file)
                                ->map($databaseWrites);
                        } catch(Exception $exception) {
                            Log::error("Error writing to database: " . $exception->getMessage());
                        }
                    }
                }
            }
        } else {
            throw new Exception('Unexpected response from AI model');
        }
    }

    /**
     * Cleans the AI Model responses to make sure we have valid JSON, if the response is JSON
     */
    public static function cleanContent($content): string
    {
        // Remove any ```json and trailing ``` from content if they are present
        return preg_replace('/^```json\n(.*)\n```$/s', '$1', trim($content));
    }
}
