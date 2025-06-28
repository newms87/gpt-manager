<?php

namespace App\Api\OpenAi;

use App\Api\AgentApiContracts\AgentApiContract;
use App\Api\OpenAi\Classes\OpenAiMessageFormatter;
use App\Api\OpenAi\Classes\OpenAiResponsesResponse;
use App\Api\Options\ResponsesApiOptions;
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
     */
    public function responses(string $model, array $messages, ResponsesApiOptions $options): OpenAiResponsesResponse
    {
        // Build request body for Responses API
        $requestBody = [
                'model' => $model,
            ] + $options->toArray();

        // Convert messages to proper Responses API input format
        $requestBody['input'] = $this->formatter()->convertRawMessagesToResponsesApiInput($messages);

        // Regular request (no streaming in this method)
        $response = $this->post('responses', $requestBody)->json();

        return OpenAiResponsesResponse::make($response);
    }

    /**
     * Execute streaming Responses API call with real-time message updates
     */
    public function streamResponses(string $model, array $messages, ResponsesApiOptions $options, AgentThreadMessage $streamMessage): OpenAiResponsesResponse
    {
        // Build request body for streaming Responses API
        $requestBody = [
                'model'  => $model,
                'stream' => true, // Always stream for this method
            ] + $options->toArray();

        // Convert messages to proper Responses API input format
        $requestBody['input'] = $this->formatter()->convertRawMessagesToResponsesApiInput($messages);

        // Use streaming request with callback
        $response = $this->post('responses', $requestBody, [
            'stream'          => true,
            'stream_callback' => function ($chunk) use ($streamMessage) {
                // Parse Server-Sent Events format for Responses API
                if (str_starts_with($chunk, 'data: ')) {
                    $data = substr($chunk, 6);
                    if ($data !== '[DONE]') {
                        $json = json_decode($data, true);

                        // Handle response.output_text.delta for streaming text
                        if (isset($json['type']) && $json['type'] === 'response.output_text.delta' && isset($json['delta'])) {
                            // Update message content incrementally
                            $streamMessage->content .= $json['delta'];
                            $streamMessage->save();

                            // Broadcast streaming event
                            broadcast(new AgentThreadMessageStreamEvent(
                                $streamMessage,
                                $streamMessage->content,
                                false
                            ));
                        } // Handle response.output_text.done for final text
                        elseif (isset($json['type']) && $json['type'] === 'response.output_text.done' && isset($json['text'])) {
                            // Set final content
                            $streamMessage->content = $json['text'];
                            $streamMessage->save();
                        }
                    }
                }
            },
        ]);

        // Broadcast completion event
        broadcast(new AgentThreadMessageStreamEvent(
            $streamMessage,
            $streamMessage->content,
            true
        ));

        // Return the final response
        return OpenAiResponsesResponse::make($response->json());
    }

}
