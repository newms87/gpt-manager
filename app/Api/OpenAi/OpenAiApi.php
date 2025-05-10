<?php

namespace App\Api\OpenAi;

use App\Api\AgentApiContracts\AgentApiContract;
use App\Api\AgentApiContracts\AgentCompletionResponseContract;
use App\Api\OpenAi\Classes\OpenAiCompletionResponse;
use App\Api\OpenAi\Classes\OpenAiMessageFormatter;
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

    public function complete(string $model, array $messages, array $options): OpenAiCompletionResponse|AgentCompletionResponseContract
    {
        $response = $this->post('chat/completions', [
                'model'    => $model,
                'messages' => $messages,
            ] + $options)->json();

        return OpenAiCompletionResponse::make($response);
    }

    public function formatter(): OpenAiMessageFormatter
    {
        return app(OpenAiMessageFormatter::class);
    }
}
