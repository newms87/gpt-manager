<?php

namespace App\Teams\TortGuard;

use App\Http\Controllers\Controller;
use App\Models\Agent\Agent;
use App\Models\TeamObject\TeamObject;
use App\Repositories\Tortguard\TortguardRepository;
use App\Resources\Tortguard\DrugSideEffectResource;
use App\Resources\Tortguard\DrugSideEffectSearchResultResource;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Log;
use Newms87\Danx\Exceptions\ValidationError;
use Throwable;

class TortguardController extends Controller
{
    /**
     * @return array
     * @throws Exception
     */
    public function getDashboardData(): array
    {
        $DrugSideEffectObjects = TeamObject::where('type', 'DrugSideEffect')
            ->whereHas('relationships', fn(Builder $builder) => $builder->where('relationship_name', 'product'))
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        $drugSideEffects = [];
        foreach($DrugSideEffectObjects as $DrugSideEffect) {
            try {
                $drugSideEffects[] = DrugSideEffectResource::dashboard($DrugSideEffect);
            } catch(Throwable|Exception|ValidationError $e) {
                Log::error('Error getting Drug Side Effect details: ' . $e->getMessage(), ['exception' => $e]);
            }
        }

        return [
            'drugSideEffects' => $drugSideEffects,
        ];
    }

    /**
     * Perform a search using the designated Search Agent
     */
    public function search(): array
    {
        $agentName = 'Drug Side-Effect Search';
        $query     = request()->input('query');
        $agent     = Agent::where('team_id', team()->id)->firstWhere('name', $agentName);

        if (!$agent) {
            throw new ValidationError("$agentName not found");
        }

        if (!$query) {
            throw new ValidationError('Search query is required');
        }

        $results = app(TortguardRepository::class)->search($agent, $query);

        return [
            'success' => true,
            'results' => DrugSideEffectSearchResultResource::collection($results),
        ];
    }

    public function research(): array
    {
        $searchResult     = request()->input('input');
        $workflowRunNames = app(TortguardRepository::class)->research($searchResult);

        return ['success' => true, 'workflowRunNames' => $workflowRunNames];
    }

    public function getDrugSideEffect(int $id): array
    {
        $DrugSideEffect = TeamObject::where('type', 'DrugSideEffect')->find($id);

        if (!$DrugSideEffect) {
            throw new ValidationError('Drug Side Effect not found');
        }

        return DrugSideEffectResource::details($DrugSideEffect);
    }
}
