<?php

namespace Tests\Feature\MockData;

use App\Api\OpenAi\Classes\OpenAiCompletionResponse;
use App\Api\OpenAi\OpenAiApi;
use App\Models\Agent\Agent;
use App\Models\Workflow\Workflow;
use App\Models\Workflow\WorkflowJob;
use Tests\TestCase;

/**
 * @mixin TestCase
 */
trait AiMockData
{
    public function openAiWorkflowJob(Workflow $workflow = null, $attributes = [])
    {
        if (!$workflow) {
            $workflow = Workflow::factory()->create();
        }
        $agent = $this->openAiAgent();

        return WorkflowJob::factory()->recycle($workflow)->recycle($agent)->hasWorkflowAssignments()->create($attributes);
    }

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

    public function mocksOpenAiCompletionResponse($content = 'Mock completion message content', $finishReason = 'stop', $usage = null)
    {
        return $this->mock(OpenAiApi::class)->shouldReceive('complete')->andReturn(OpenAiCompletionResponse::make([
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
