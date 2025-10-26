<?php

namespace App\Repositories;

use App\Models\Demand\DemandTemplate;
use App\Services\DemandTemplate\DemandTemplateService;
use Illuminate\Database\Eloquent\Builder;
use Newms87\Danx\Repositories\ActionRepository;

class DemandTemplateRepository extends ActionRepository
{
    public static string $model = DemandTemplate::class;

    public function query(): Builder
    {
        return parent::query()->where('team_id', team()?->id ?: 0)->with(['storedFile', 'user']);
    }

    /**
     * {@inheritDoc}
     */
    public function applyAction(string $action, $model = null, ?array $data = null)
    {
        $service = app(DemandTemplateService::class);

        return match ($action) {
            'create'                   => $service->createTemplate($data ?? []),
            'update'                   => $service->updateTemplate($model, $data ?? []),
            'fetch-template-variables' => $service->fetchTemplateVariables($model),
            default                    => parent::applyAction($action, $model, $data)
        };
    }
}
