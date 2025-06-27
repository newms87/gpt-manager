<?php

namespace App\Api\OpenAi;

use App\Api\AgentApiContracts\AgentApiContract;
use App\Api\AgentApiContracts\AgentCompletionResponseContract;
use App\Api\Options\ResponsesApiOptions;
use App\Api\OpenAi\Classes\OpenAiCompletionResponse;
use App\Api\OpenAi\Classes\OpenAiMessageFormatter;
use App\Events\AgentThreadMessageStreamEvent;
use App\Models\Agent\AgentThreadMessage;
use Newms87\Danx\Api\BearerTokenApi;
use Newms87\Danx\Exceptions\ApiException;

class OpenAiApi extends BearerTokenApi implements AgentApiContract
{
    protected array $rateLimits = [
        // 5 requests per second, wait 1 second between attempts
        ['limit' => 5, 'interval' => 1, 'waitPerAttempt' => 1],
    ];

    public static string $serviceName = 'OpenAI';

    public function __construct()
    {
        $this->baseApiUrl = config('openai.api_url');
        $this->token      = config('openai.api_key');
    }

    public function getModels(): array
    {
        $data = $this->get('models')->json('data');

        if (!$data) {
            throw new ApiException('Failed to fetch models from OpenAI API');
        }

        return $data;
    }

    public function formatter(): OpenAiMessageFormatter
    {
        return app(OpenAiMessageFormatter::class);
    }

    /**
     * Execute using the Responses API
     * 
     * @param string $model
     * @param array  $messages
     * @param ResponsesApiOptions $options
     * @return OpenAiCompletionResponse|AgentCompletionResponseContract
     */
    public function responses(string $model, array $messages, ResponsesApiOptions $options): OpenAiCompletionResponse|AgentCompletionResponseContract
    {
        // Build request body for Responses API
        $requestBody = [
            'model' => $model,
        ];
        
        // Add service tier if specified
        if ($options->getServiceTier() !== 'auto') {
            $requestBody['service_tier'] = $options->getServiceTier();
        }
        
        // Handle system instructions (proper Responses API way)
        if ($options->getInstructions() !== null) {
            $requestBody['instructions'] = $options->getInstructions();
        }
        
        // Convert messages to input - extract user messages for input
        $userMessages = [];
        foreach ($messages as $message) {
            if (isset($message['role']) && $message['role'] === 'user' && isset($message['content'])) {
                $userMessages[] = $message['content'];
            }
        }
        
        // Use explicit input if provided, otherwise use user messages
        if ($options->getInput() !== null) {
            $requestBody['input'] = $options->getInput();
        } elseif (!empty($userMessages)) {
            // If multiple user messages, join them
            $requestBody['input'] = implode('\n\n', $userMessages);
        }
        
        if ($options->getPreviousResponseId() !== null) {
            $requestBody['previous_response_id'] = $options->getPreviousResponseId();
        }
        
        // Add reasoning configuration if specified
        $reasoning = $options->getReasoning();
        if (!empty($reasoning)) {
            $requestBody['reasoning'] = $reasoning;
        }
        
        // Regular request (no streaming in this method)
        $response = $this->post('responses', $requestBody)->json();
        
        return OpenAiCompletionResponse::make($response);
    }
    
    /**
     * Execute streaming Responses API call with real-time message updates
     */
    public function streamResponses(string $model, array $messages, ResponsesApiOptions $options, AgentThreadMessage $streamMessage): OpenAiCompletionResponse|AgentCompletionResponseContract
    {
        // Build request body for streaming Responses API
        $requestBody = [
            'model' => $model,
            'stream' => true, // Always stream for this method
        ];
        
        // Add service tier if specified
        if ($options->getServiceTier() !== 'auto') {
            $requestBody['service_tier'] = $options->getServiceTier();
        }
        
        // Handle system instructions (proper Responses API way)
        if ($options->getInstructions() !== null) {
            $requestBody['instructions'] = $options->getInstructions();
        }
        
        // Convert messages to input - extract user messages for input
        $userMessages = [];
        foreach ($messages as $message) {
            if (isset($message['role']) && $message['role'] === 'user' && isset($message['content'])) {
                $userMessages[] = $message['content'];
            }
        }
        
        // Use explicit input if provided, otherwise use user messages
        if ($options->getInput() !== null) {
            $requestBody['input'] = $options->getInput();
        } elseif (!empty($userMessages)) {
            // If multiple user messages, join them
            $requestBody['input'] = implode('\n\n', $userMessages);
        }
        
        if ($options->getPreviousResponseId() !== null) {
            $requestBody['previous_response_id'] = $options->getPreviousResponseId();
        }
        
        // Add reasoning configuration if specified
        $reasoning = $options->getReasoning();
        if (!empty($reasoning)) {
            $requestBody['reasoning'] = $reasoning;
        }
        
        // Use streaming request with callback
        $response = $this->post('responses', $requestBody, [
            'stream' => true,
            'stream_callback' => function($chunk) use ($streamMessage) {
                // Parse Server-Sent Events format for Responses API
                if (str_starts_with($chunk, 'data: ')) {
                    $data = substr($chunk, 6);
                    if ($data !== '[DONE]') {
                        $json = json_decode($data, true);
                        // Responses API streaming format - check output content
                        if (isset($json['output'][0]['content'])) {
                            foreach ($json['output'][0]['content'] as $content) {
                                if (isset($content['type']) && $content['type'] === 'text' && isset($content['text'])) {
                                    // Update message content
                                    $streamMessage->content .= $content['text'];
                                    $streamMessage->save();
                                    
                                    // Broadcast streaming event
                                    broadcast(new AgentThreadMessageStreamEvent(
                                        $streamMessage,
                                        $streamMessage->content,
                                        false
                                    ));
                                }
                            }
                        }
                    }
                }
            }
        ]);
        
        // Broadcast completion event
        broadcast(new AgentThreadMessageStreamEvent(
            $streamMessage,
            $streamMessage->content,
            true
        ));
        
        // Return the final response
        return OpenAiCompletionResponse::make($response->json());
    }
}
