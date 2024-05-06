<?php

namespace App\Api\OpenAi;

use App\Api\AgentApiContracts\AgentApiContract;
use App\Api\OpenAi\Classes\OpenAiCompletionResponse;
use Flytedan\DanxLaravel\Api\BearerTokenApi;
use Flytedan\DanxLaravel\Exceptions\ApiException;

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

        return collect($data)->pluck('id')->toArray();
    }

    public function complete(string $model, array $messages, array $options): OpenAiCompletionResponse
    {
        $response = $this->post('chat/completions', [
                'model'    => $model,
                'messages' => $messages,
            ] + $options)->json();

        return OpenAiCompletionResponse::make($response);
    }
}
