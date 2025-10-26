<?php

namespace App\Api\PerplexityAi;

use App\Api\AgentApiContracts\AgentApiContract;
use App\Api\AgentApiContracts\AgentCompletionResponseContract;
use App\Api\Options\ResponsesApiOptions;
use App\Api\PerplexityAi\Classes\PerplexityAiMessageFormatter;
use App\Api\PerplexityAi\Classes\PerplexityAiResponsesResponse;
use App\Models\Agent\AgentThreadMessage;
use Newms87\Danx\Api\BearerTokenApi;

class PerplexityAiApi extends BearerTokenApi implements AgentApiContract
{
    protected array $rateLimits = [
        [
            'limit'          => 19,
            'interval'       => 60,
            'waitPerAttempt' => 3,
        ],
    ];

    public static string $serviceName = 'PerplexityAI';

    public function __construct()
    {
        $this->baseApiUrl = config('perplexityai.api_url');
        $this->token      = config('perplexityai.api_key');
    }

    public function formatter(): PerplexityAiMessageFormatter
    {
        return app(PerplexityAiMessageFormatter::class);
    }

    /**
     * PerplexityAI doesn't support Responses API - fallback to chat/completions
     */
    public function responses(string $model, array $messages, ResponsesApiOptions $options): AgentCompletionResponseContract
    {
        // Convert ResponsesApiOptions to basic completion options
        $completionOptions = [
            'temperature' => .2, // PerplexityAI default
        ];

        // Add instructions as a system message if available
        if ($options->getInstructions()) {
            // Add the instructions as a system message at the beginning
            array_unshift($messages, [
                'role'    => 'system',
                'content' => $options->getInstructions(),
            ]);
        }

        // Set HTTP client timeout from options
        $httpOptions = [];
        if ($options->getTimeout()) {
            $httpOptions['timeout'] = $options->getTimeout();
        }

        $response = $this->post('chat/completions', [
            'model'    => $model,
            'messages' => $messages,
        ] + $completionOptions, $httpOptions)->json();

        return PerplexityAiResponsesResponse::make($response);
    }

    /**
     * PerplexityAI doesn't support streaming Responses API
     */
    public function streamResponses(string $model, array $messages, ResponsesApiOptions $options, AgentThreadMessage $streamMessage): AgentCompletionResponseContract
    {
        throw new \RuntimeException('PerplexityAI does not support streaming. Please disable the stream option in your agent configuration.');
    }
}
