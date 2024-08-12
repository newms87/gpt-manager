<?php

namespace App\Resources\Tortguard;

use App\Models\TeamObject\TeamObject;
use App\Models\Workflow\WorkflowRun;
use App\Resources\Workflow\WorkflowRunResource;
use Illuminate\Database\Eloquent\Model;

class DrugSideEffectResource extends TeamObjectResource
{
    /**
     * @param TeamObject $model
     */
    public static function details(Model $model): array
    {
        $product       = $model->relatedObjects('product')->first();
        $company       = $product?->relatedObjects('company')->first();
        $workflowRunId = $model->meta['workflow_run_id'] ?? null;
        $workflowRun   = $workflowRunId ? WorkflowRun::find($workflowRunId) : null;

        return static::make($model, [
            'product'     => DrugProductResource::make($product, [
                'company' => CompanyResource::make($company),
            ]),
            'workflowRun' => WorkflowRunResource::make($workflowRun),
        ]);
    }
}
