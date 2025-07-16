<?php

namespace Tests\Feature\MockData;

use App\Api\OpenAi\Classes\OpenAiMessageFormatter;
use App\Api\OpenAi\OpenAiApi;
use App\Models\Agent\Agent;
use Mockery\CompositeExpectation;
use Tests\Feature\Api\TestAi\Classes\TestAiCompletionResponse;
use Tests\TestCase;

/**
 * @mixin TestCase
 */
trait AiMockData
{
    public function openAiAgent($attributes = [], $user = null): Agent
    {
        if (!$user) {
            $user = user();
        }

        return Agent::factory()->recycle($user)->create($attributes);
    }

    public function mocksOpenAiNotCalled()
    {
        $this->mock(OpenAiApi::class)->shouldNotReceive('responses');
    }

    public function mocksOpenAiResponsesResponse($content = 'Mock response message content', $status = 'completed', $usage = null): CompositeExpectation
    {
        return $this->mock(OpenAiApi::class)
            ->shouldReceive('formatter')->andReturn(new OpenAiMessageFormatter)
            ->shouldReceive('responses')->andReturn(TestAiCompletionResponse::make([
                'id'     => 'resp_mock_12345',
                'status' => $status,
                'output' => [
                    [
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => $content,
                            ],
                        ],
                    ],
                ],
                'usage'  => $usage ?: ['input_tokens' => 10, 'output_tokens' => 6],
            ]));
    }
}
