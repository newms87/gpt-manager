<?php

namespace App\Repositories;

use App\Models\DemandTemplate;
use App\Services\DemandTemplate\DemandTemplateService;
use Illuminate\Database\Eloquent\Builder;
use Newms87\Danx\Repositories\ActionRepository;

class DemandTemplateRepository extends ActionRepository
{
    public static string $model = DemandTemplate::class;

    public function query(): Builder
    {
        return parent::query()
            ->with(['storedFile', 'user'])
            ->where('team_id', team()->id);
    }

    /**
     * @inheritDoc
     */
    public function applyAction(string $action, $model = null, ?array $data = null)
    {
        $service = app(DemandTemplateService::class);
        
        return match ($action) {
            'create' => $service->createTemplate($data ?? []),
            'update' => $service->updateTemplate($model, $data ?? []),
            'delete' => $model->delete(),
            default => parent::applyAction($action, $model, $data)
        };
    }

    public function getActiveTemplates()
    {
        return $this->query()
            ->active()
            ->orderBy('name')
            ->get();
    }

    public function toggleActive(DemandTemplate $template): DemandTemplate
    {
        return app(DemandTemplateService::class)->toggleActive($template);
    }
}