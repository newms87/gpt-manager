<?php

namespace Tests\Feature\Api\TestAi;

use App\Api\AgentApiContracts\AgentApiContract;
use App\Api\AgentApiContracts\AgentMessageFormatterContract;
use App\Api\OpenAi\Classes\OpenAiMessageFormatter;
use App\Api\Options\ResponsesApiOptions;
use Tests\Feature\Api\TestAi\Classes\TestAiCompletionResponse;

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

    public function responses(string $model, array $messages, ResponsesApiOptions $options): \App\Api\AgentApiContracts\AgentCompletionResponseContract
    {
        return TestAiCompletionResponse::make([
            'messages' => $messages,
            'options'  => $options->toArray($model),
        ]);
    }

    public function streamResponses(string $model, array $messages, ResponsesApiOptions $options, \App\Models\Agent\AgentThreadMessage $streamMessage): \App\Api\AgentApiContracts\AgentCompletionResponseContract
    {
        return TestAiCompletionResponse::make([
            'messages'  => $messages,
            'options'   => $options->toArray($model),
            'streaming' => true,
        ]);
    }
}
