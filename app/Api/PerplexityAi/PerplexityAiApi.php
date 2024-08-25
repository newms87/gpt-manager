<?php

namespace App\Api\PerplexityAi;

use App\Api\AgentApiContracts\AgentApiContract;
use App\Api\AgentApiContracts\AgentCompletionResponseContract;
use App\Api\PerplexityAi\Classes\PerplexityAiCompletionResponse;
use App\Api\PerplexityAi\Classes\PerplexityAiMessageFormatter;
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

    public function complete(string $model, array $messages, array $options = []): PerplexityAiCompletionResponse|AgentCompletionResponseContract
    {
        $options += [
            'temperature' => .2,
        ];

        $response = $this->post('chat/completions', [
                'model'    => $model,
                'messages' => $messages,
            ] + $options)->json();

        return PerplexityAiCompletionResponse::make($response);
    }

    public function formatter(): PerplexityAiMessageFormatter
    {
        return app(PerplexityAiMessageFormatter::class);
    }
}
