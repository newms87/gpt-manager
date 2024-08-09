<?php

namespace App\Teams\TortGuard;

use App\Http\Controllers\Controller;
use App\Models\Agent\Agent;
use App\Models\TeamObject\TeamObject;
use App\Models\Workflow\Workflow;
use App\Models\Workflow\WorkflowInput;
use App\Repositories\ThreadRepository;
use App\Resources\Tortguard\DrugInjuryResource;
use App\Resources\Workflow\WorkflowRunResource;
use App\Services\AgentThread\AgentThreadService;
use App\Services\Workflow\WorkflowService;
use Exception;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Helpers\StringHelper;

class TortguardController extends Controller
{
    /**
     * @return array
     * @throws Exception
     */
    public function getDashboardData(): array
    {
        $drugInjuryObjects = TeamObject::where('type', 'DrugInjury')->get();

        $drugInjuries = [];
        foreach($drugInjuryObjects as $drugInjury) {
            $drugInjuries[] = DrugInjuryResource::details($drugInjury);
        }

        return [
            'drugInjuries' => $drugInjuries,
        ];
    }

    /**
     * Perform a search using the designated Search Agent
     */
    public function search(): array
    {
        $query = request()->input('query');
        $agent = Agent::where('team_id', team()->id)->firstWhere('name', 'Search Agent');

        if (!$agent) {
            throw new ValidationError('Search Agent not found');
        }

        $thread = $agent->threads()->create([
            'user_id'  => user()->id,
            'team_id'  => $agent->team_id,
            'name'     => 'Search: ' . $query,
            'agent_id' => $agent->id,
        ]);

        app(ThreadRepository::class)->addMessageToThread($thread, 'Query: ' . $query);

        $threadRun = app(AgentThreadService::class)->run($thread, dispatch: false);

        if (!$threadRun->lastMessage) {
            return [
                'error' => "No results found for query: $query",
            ];
        }

        return ['success' => true, ...StringHelper::safeJsonDecode($threadRun->lastMessage->content, 100000)];
    }

    public function research(): array
    {
        $product  = request()->input('product');
        $injury   = request()->input('injury');
        $workflow = Workflow::where('team_id', team()->id)->firstWhere('name', 'Drug Injury Researcher');

        if (!$workflow) {
            throw new ValidationError('Drug Injury Researcher workflow not found');
        }

        $workflowInput = WorkflowInput::make()->forceFill([
            'team_id' => team()->id,
            'user_id' => user()->id,
            'name'    => 'Research: ' . $product . ' - ' . $injury,
            'content' => json_encode([
                'product' => $product,
                'injury'  => $injury,
            ]),
        ]);
        $workflowInput->save();

        $workflowRun = app(WorkflowService::class)->run($workflow, $workflowInput);

        return ['success' => true, 'workflowRun' => WorkflowRunResource::make($workflowRun)];
    }
}
