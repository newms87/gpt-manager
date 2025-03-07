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

        return Agent::factory()->recycle($user)->create([
            'api' => OpenAiApi::$serviceName,
            ...$attributes,
        ]);
    }

    public function mocksOpenAiNotCalled()
    {
        $this->mock(OpenAiApi::class)->shouldNotReceive('complete');
    }

    public function mocksOpenAiCompletionResponse($content = 'Mock completion message content', $finishReason = 'stop', $usage = null): CompositeExpectation
    {
        return $this->mock(OpenAiApi::class)
            ->shouldReceive('formatter')->andReturn(new OpenAiMessageFormatter)
            ->shouldReceive('complete')->andReturn(TestAiCompletionResponse::make([
                'choices' => [
                    [
                        'message'       => ['content' => $content],
                        'finish_reason' => $finishReason,
                    ],
                ],
                'usage'   => $usage ?: ['prompt_tokens' => 10, 'completion_tokens' => 6],
            ]));
    }
}
