<?php

namespace App\Api\OpenAI;

use App\Api\AgentModelApiContract;
use Flytedan\DanxLaravel\Api\BearerTokenApi;
use Flytedan\DanxLaravel\Exceptions\ApiException;

class OpenAIApi extends BearerTokenApi implements AgentModelApiContract
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

    public function complete(string $model, float $temperature, array $messages): array
    {
        return $this->post('chat/completions', [
            'model'       => $model,
            'messages'    => $messages,
            'temperature' => $temperature,
        ])->json();
    }
}
