<?php

namespace App\Repositories\Tortguard;

use App\Models\Agent\Agent;
use App\Models\TeamObject\TeamObject;
use App\Models\Workflow\Workflow;
use App\Models\Workflow\WorkflowInput;
use App\Models\Workflow\WorkflowRun;
use App\Repositories\ThreadRepository;
use App\Services\AgentThread\AgentThreadService;
use App\Services\Workflow\WorkflowService;
use Newms87\Danx\Exceptions\ValidationError;
use Str;

class TortguardRepository
{
    public function search(Agent $agent, string $query): array
    {
        $thread = $agent->threads()->create([
            'user_id'  => user()->id,
            'team_id'  => $agent->team_id,
            'name'     => 'Search: ' . $query,
            'agent_id' => $agent->id,
        ]);

        app(ThreadRepository::class)->addMessageToThread($thread, 'User search query: ' . $query);

        $threadRun = app(AgentThreadService::class)->run($thread, dispatch: false);

        if (!$threadRun->lastMessage) {
            return [
                'error' => "No results found for query: $query",
            ];
        }

        $searchResults = $threadRun->lastMessage->getJsonContent();

        if (!array_key_exists('results', $searchResults)) {
            throw new ValidationError("Search results key was not set in response from $agent");
        }

        return $searchResults['results'] ?: [];
    }

    public function research($searchResult): WorkflowRun
    {
        $productName = $searchResult['product_name'] ?? null;
        $productUrl  = $searchResult['product_name'] ?? null;
        $description = $searchResult['description'] ?? null;
        $companies   = $searchResult['companies'] ?? null;
        $sideEffect  = $searchResult['side_effect'] ?? null;

        $researchWorkflowName = 'Drug Side-Effect Researcher';
        $workflow             = Workflow::where('team_id', team()->id)->firstWhere('name', $researchWorkflowName);

        if (!$workflow) {
            throw new ValidationError("$researchWorkflowName workflow not found");
        }

        if (!$productName || !$sideEffect) {
            throw new ValidationError('Product Name and Side Effect are required for research');
        }

        $DrugSideEffect = TeamObject::firstOrCreate([
            'ref'  => Str::slug($productName . ':' . $sideEffect),
            'type' => 'DrugSideEffect',
            'name' => $productName . ': ' . $sideEffect,
        ], [
            'description' => $description,
        ]);

        $drugProduct = TeamObject::firstOrCreate([
            'ref'  => Str::slug($productName),
            'type' => 'DrugProduct',
            'name' => $productName,
        ], [
            'url' => $productUrl,
        ]);

        foreach($companies as $company) {
            $companyName = $company['name'] ?? $company;
            $parentName  = $company['parent_name'] ?? null;
            $company     = TeamObject::firstOrCreate([
                'ref'  => Str::slug($companyName),
                'type' => 'Company',
                'name' => $companyName,
                'meta' => $parentName ? ['is_subsidiary' => true] : [],
            ]);

            $drugProduct->relationships()->create([
                'related_object_id' => $company->id,
                'relationship_name' => 'companies',
            ]);

            if ($parentName) {
                $parent = TeamObject::firstOrCreate([
                    'ref'  => Str::slug($parentName),
                    'type' => 'Company',
                    'name' => $parentName,
                ]);

                $company->relationships()->create([
                    'related_object_id' => $parent->id,
                    'relationship_name' => 'parent',
                ]);
            }
        }

        $DrugSideEffect->relationships()->create([
            'related_object_id' => $drugProduct->id,
            'relationship_name' => 'product',
        ]);


        $workflowInput = WorkflowInput::make()->forceFill([
            'team_id' => team()->id,
            'user_id' => user()->id,
            'name'    => 'Research: ' . $productName . ' - ' . $sideEffect,
            'content' => json_encode([
                'product'     => $productName,
                'side_effect' => $sideEffect,
            ]),
        ]);
        $workflowInput->save();

        $workflowRun = app(WorkflowService::class)->run($workflow, $workflowInput);

        $DrugSideEffect->meta = [
            'workflow_run_id' => $workflowRun->id,
        ];
        $DrugSideEffect->save();

        return $workflowRun;
    }
}
