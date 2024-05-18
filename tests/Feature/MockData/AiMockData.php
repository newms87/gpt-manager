<?php

namespace Tests\Feature\MockData;

use App\Api\OpenAi\Classes\OpenAiCompletionResponse;
use App\Api\OpenAi\OpenAiApi;
use App\Models\Agent\Agent;
use Tests\TestCase;

/**
 * @mixin TestCase
 */
trait AiMockData
{
    public function openAiAgent($user = null)
    {
        if (!$user) {
            $user = user();
        }

        return Agent::factory()->recycle($user)->create([
            'api' => OpenAiApi::$serviceName,
        ]);
    }

    public function mocksOpenAiNotCalled()
    {
        $this->mock(OpenAiApi::class)->shouldNotReceive('complete');
    }

    public function mocksOpenAiCompletionResponse($content = 'Mock completion message content', $finishReason = 'stop', $usage = null): void
    {
        $this->mock(OpenAiApi::class)->shouldReceive('complete')->once()->andReturn(OpenAiCompletionResponse::make([
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
