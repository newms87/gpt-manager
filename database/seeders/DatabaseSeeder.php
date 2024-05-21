<?php

namespace Database\Seeders;

use App\Api\OpenAi\OpenAiApi;
use App\Models\Agent\Agent;
use App\Models\Agent\Thread;
use App\Models\Shared\InputSource;
use App\Models\Team\Team;
use App\Models\User;
use App\Models\Workflow\Workflow;
use App\Models\Workflow\WorkflowJob;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $team = Team::firstWhere('name', 'Team Dan');
        if (!$team) {
            $team = Team::factory()->create([
                'name' => 'Team Dan',
            ]);
        }

        if (User::where('email', 'dan@sagesweeper.com')->doesntExist()) {
            User::factory()->create([
                'name'    => 'Daniel Newman',
                'email'   => 'dan@sagesweeper.com',
                'team_id' => $team,
            ]);
        }

        for($i = 0; $i < 3; $i++) {
            $threads = Thread::factory()->forTeam($team)->count(fake()->numberBetween(0, 3));
            Agent::factory()->has($threads)->recycle($team)->create();
        }

        $questionAgent  = Agent::factory()->recycle($team)->create([
            'name'        => 'Question Maker',
            'api'         => OpenAiApi::$serviceName,
            'model'       => config('ai.default_model'),
            'description' => 'Generates questions based on the provided topic',
            'prompt'      => "Based on the provided topic, generate 3 deeply profound questions. Your response will be a valid JSON object in the form:\n\n```json\n{\"questions\":[\"Question 1\",\"Question 2\",\"Question 3\"]}\n```",
            'tools'       => [],
        ]);
        $answerAgent    = Agent::factory()->recycle($team)->create([
            'name'        => 'Question Answerer',
            'api'         => OpenAiApi::$serviceName,
            'model'       => config('ai.default_model'),
            'description' => 'Answers questions based on the provided topic',
            'prompt'      => "Answer the given question to the best of your knowledge. Give a robust response covering all facets of the topic",
            'tools'       => [],
        ]);
        $validatorAgent = Agent::factory()->recycle($team)->create([
            'name'        => 'Answer Validator',
            'api'         => OpenAiApi::$serviceName,
            'model'       => config('ai.default_model'),
            'description' => 'Validates the question and answer pair to ensure they are related and the answer is valid',
            'prompt'      => "Check the provided topic, the generated question and the provided answer and verify the question is related to the topic and the answer is a valid response to the question. Your response will be a valid JSON object in the form:\n\n```json\n{\"valid\":true}\n\nor\n\n{\"valid\":false,\"reason\":\"The reason the answer is invalid\"}\n```",
            'tools'       => [],
        ]);

        $workflow = Workflow::factory()->create([
            'name'        => 'Question and Answer Workflow',
            'description' => 'A workflow to generate questions, answer them and validate the answers',
        ]);

        $questionJob = WorkflowJob::factory()
            ->recycle($workflow)
            ->hasWorkflowAssignments(1, ['agent_id' => $questionAgent])
            ->create([
                'name'             => 'Ask Question Job',
                'description'      => 'Generate a question based on a topic',
                'use_input_source' => true,
            ]);
        $answerJob   = WorkflowJob::factory()
            ->recycle($workflow)
            ->hasWorkflowAssignments(1, ['agent_id' => $answerAgent])
            ->hasDependencies(1, ['depends_on_workflow_job_id' => $questionJob, 'group_by' => 'questions'])
            ->create([
                'name'             => 'Answer Question Job',
                'description'      => 'Answer a question based on a topic',
                'use_input_source' => false,
            ]);
        WorkflowJob::factory()
            ->recycle($workflow)
            ->hasWorkflowAssignments(1, ['agent_id' => $validatorAgent])
            ->hasDependencies(1, ['depends_on_workflow_job_id' => $answerJob])
            ->create([
                'name'             => 'Validates a Question and Answer created by AI agents',
                'description'      => 'Validate the question and answer pair to ensure they are related and the answer is valid',
                'use_input_source' => true,
            ]);

        InputSource::factory()->create([
            'name'        => 'Topic: History of Computers',
            'description' => 'The topic to generate questions and answers for',
            'content'     => "Topic: History of Computers",
        ]);
        InputSource::factory()->create([
            'name'        => 'Topic: Tom Hanks',
            'description' => 'The topic to generate questions and answers for',
            'content'     => "Topic: Tom Hanks",
        ]);
    }
}
