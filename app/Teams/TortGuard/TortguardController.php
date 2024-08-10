<?php

namespace App\Teams\TortGuard;

use App\Http\Controllers\Controller;
use App\Models\Agent\Agent;
use App\Models\TeamObject\TeamObject;
use App\Repositories\ThreadRepository;
use App\Repositories\Tortguard\TortguardRepository;
use App\Resources\Tortguard\DrugInjuryResource;
use App\Resources\Workflow\WorkflowRunResource;
use App\Services\AgentThread\AgentThreadService;
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
        $drugInjuryObjects = TeamObject::where('type', 'DrugInjury')->orderByDesc('id')->limit(8)->get();

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
        $searchResult = json_decode(request()->input('search_result'), true);
        $workflowRun  = app(TortguardRepository::class)->research($searchResult);

        return ['success' => true, 'workflowRun' => WorkflowRunResource::make($workflowRun)];
    }

    public function getDrugInjury(int $id): array
    {
        $drugInjury = TeamObject::where('type', 'DrugInjury')->find($id);

        if (!$drugInjury) {
            throw new ValidationError('Drug Injury not found');
        }

        return DrugInjuryResource::details($drugInjury);
    }
}
