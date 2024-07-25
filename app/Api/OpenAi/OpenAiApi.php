<?php

namespace App\Api\OpenAi;

use App\Api\AgentApiContracts\AgentApiContract;
use App\Api\OpenAi\Classes\OpenAiCompletionResponse;
use App\Api\OpenAi\Classes\OpenAiMessageFormatter;
use Newms87\Danx\Api\BearerTokenApi;
use Newms87\Danx\Exceptions\ApiException;

class OpenAiApi extends BearerTokenApi implements AgentApiContract
{
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

    public function complete(string $model, array $messages, array $options): OpenAiCompletionResponse
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
