<?php

namespace Tests\Feature\TestApi\TestAiApi;

use App\Api\AgentApiContracts\AgentApiContract;
use App\Api\AgentApiContracts\AgentMessageFormatterContract;
use App\Api\OpenAi\Classes\OpenAiMessageFormatter;
use Tests\Feature\TestApi\TestAiApi\Classes\TestAiCompletionResponse;

class TestAiApi implements AgentApiContract
{
    public static string $serviceName = 'TestAI';

    public function getModels(): array
    {
        return [
            [
                'id'          => 1,
                'name'        => 'test-model',
                'title'       => 'Test Model',
                'description' => 'Test Model Description',
            ],
        ];
    }

    public function complete(string $model, array $messages, array $options): TestAiCompletionResponse
    {
        return TestAiCompletionResponse::make([
            'messages' => $messages,
        ]);
    }

    public function formatter(): AgentMessageFormatterContract
    {
        return app(OpenAiMessageFormatter::class);
    }
}
