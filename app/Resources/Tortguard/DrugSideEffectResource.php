<?php

namespace App\Resources\Tortguard;

use App\Models\TeamObject\TeamObject;
use App\Models\Workflow\WorkflowRun;
use App\Resources\Workflow\WorkflowRunResource;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Exceptions\ValidationError;

class DrugSideEffectResource extends TeamObjectResource
{
    /**
     * @param TeamObject $model
     * @return array
     * @throws ValidationError
     */
    public static function details(Model $model): array
    {
        $product        = $model->relatedObjects('product')->first();
        $companies      = $product?->relatedObjects('companies')->get();
        $workflowRunIds = $model->meta['workflow_run_ids'] ?? null;
        $workflowRuns   = $workflowRunIds ? WorkflowRun::whereIn('id', $workflowRunIds)->get() : [];

        if (!$product) {
            throw new ValidationError('Product not found');
        }

        return static::make($model, [
            'product'      => DrugProductResource::make($product, [
                'companies' => CompanyResource::collection($companies, fn($company) => [
                    'parent' => CompanyResource::make($company->relatedObjects('parent')->first()),
                ]),
                'patents'   => PatentResource::collection($product->relatedObjects('patents')->get()),
            ]),
            'workflowRuns' => WorkflowRunResource::collection($workflowRuns),
        ]);
    }
}
