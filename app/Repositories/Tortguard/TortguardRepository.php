<?php

namespace App\Repositories\Tortguard;

use App\Models\Agent\Agent;
use App\Models\Workflow\Workflow;
use App\Models\Workflow\WorkflowInput;
use App\Models\Workflow\WorkflowRun;
use App\Repositories\TeamObjectRepository;
use App\Repositories\ThreadRepository;
use App\Services\AgentThread\AgentThreadService;
use App\Services\Workflow\WorkflowService;
use Newms87\Danx\Exceptions\ValidationError;

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

        if (!$productName || !$sideEffect) {
            throw new ValidationError('Product Name and Side Effect are required for research');
        }

        $teamObjectRepo = app(TeamObjectRepository::class);

        $drugSideEffect = $teamObjectRepo->saveTeamObject('DrugSideEffect', $productName . ': ' . $sideEffect, [
            'description' => $description,
        ]);

        $drugProduct = $teamObjectRepo->saveTeamObject('DrugProduct', $productName, [
            'url' => $productUrl,
        ]);

        foreach($companies as $company) {
            $companyName = $company['name'] ?? $company;
            $parentName  = $company['parent_name'] ?? null;

            $company = $teamObjectRepo->saveTeamObject('Company', $companyName, [
                'meta' => $parentName ? ['is_subsidiary' => true] : [],
            ]);

            $teamObjectRepo->saveTeamObjectRelationship($drugProduct, 'companies', $company);

            // If the company has a parent company, create the parent company
            if ($parentName) {
                $parentCompany = $teamObjectRepo->saveTeamObject('Company', $parentName);

                // Add the parent company to the subsidiary company
                $teamObjectRepo->saveTeamObjectRelationship($company, 'parent', $parentCompany);

                // Add the parent company to the drug product as well
                $teamObjectRepo->saveTeamObjectRelationship($drugProduct, 'companies', $parentCompany);
            }
        }

        // Add the drug Product to the Side Effect
        $teamObjectRepo->saveTeamObjectRelationship($drugSideEffect, 'product', $drugProduct);

        $researchWorkflowName = 'Drug Side-Effect Researcher';
        $workflow             = Workflow::where('team_id', team()->id)->firstWhere('name', $researchWorkflowName);

        if (!$workflow) {
            throw new ValidationError("$researchWorkflowName workflow not found");
        }

        $workflowInput = WorkflowInput::make()->forceFill([
            'team_id'          => team()->id,
            'user_id'          => user()->id,
            'name'             => 'Research: ' . $productName . ' - ' . $sideEffect,
            'team_object_type' => 'DrugSideEffect',
            'team_object_id'   => $drugSideEffect->id,
        ]);
        $workflowInput->save();

        $workflowRun = app(WorkflowService::class)->run($workflow, $workflowInput);

        $drugSideEffect->meta = [
            'workflow_run_id' => $workflowRun->id,
        ];
        $drugSideEffect->save();

        return $workflowRun;
    }
}
