<?php

namespace App\Repositories\Tortguard;

use App\Models\TeamObject\TeamObject;
use App\Models\TeamObject\TeamObjectAttribute;
use App\Models\TeamObject\TeamObjectRelation;
use App\Models\Workflow\Workflow;
use App\Models\Workflow\WorkflowInput;
use App\Models\Workflow\WorkflowRun;
use App\Services\Workflow\WorkflowService;
use Newms87\Danx\Exceptions\ValidationError;
use Str;

class TortguardRepository
{
    public function research($searchResult): WorkflowRun
    {
        $product     = $searchResult['product'] ?? null;
        $injury      = $searchResult['injury'] ?? null;
        $company     = $searchResult['company'] ?? null;
        $description = $searchResult['description'] ?? null;

        $workflow = Workflow::where('team_id', team()->id)->firstWhere('name', 'Drug Injury Researcher');

        if (!$workflow) {
            throw new ValidationError('Drug Injury Researcher workflow not found');
        }

        if (!$product || !$injury) {
            throw new ValidationError('Product and Injury are required');
        }

        $drugInjury = TeamObject::create([
            'team_id'     => team()->id,
            'ref'         => Str::slug($product . ':' . $injury),
            'type'        => 'DrugInjury',
            'name'        => $product . ': ' . $injury,
            'description' => $description,
        ]);

        $drugProduct = TeamObject::create([
            'team_id' => team()->id,
            'ref'     => Str::slug($product),
            'type'    => 'DrugProduct',
            'name'    => $product,
        ]);

        $company = TeamObject::create([
            'team_id' => team()->id,
            'ref'     => Str::slug($company),
            'type'    => 'Company',
            'name'    => $company,
        ]);

        $drugInjury->relationships()->create([
            'related_object_id' => $drugProduct->id,
            'relationship_name' => 'product',
        ]);

        $drugProduct->relationships()->create([
            'related_object_id' => $company->id,
            'relationship_name' => 'company',
        ]);

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

        return app(WorkflowService::class)->run($workflow, $workflowInput);
    }

    public function getTeamObjectAttribute(int $id): TeamObjectAttribute
    {
        return TeamObjectAttribute::findOrFail($id);
    }

    public function getTeamObjectRelation(int $id): TeamObjectRelation
    {
        return TeamObjectRelation::findOrFail($id);
    }
}
