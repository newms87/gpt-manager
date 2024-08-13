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
        $companies     = $product?->relatedObjects('companies')->get();
        $workflowRunId = $model->meta['workflow_run_id'] ?? null;
        $workflowRun   = $workflowRunId ? WorkflowRun::find($workflowRunId) : null;

        return static::make($model, [
            'product'     => DrugProductResource::make($product, [
                'companies' => CompanyResource::collection($companies, fn($company) => [
                    'parent' => CompanyResource::make($company->relatedObjects('parent')->first()),
                ]),
            ]),
            'workflowRun' => WorkflowRunResource::make($workflowRun),
        ]);
    }
}
