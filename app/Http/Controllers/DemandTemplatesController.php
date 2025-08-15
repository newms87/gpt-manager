<?php

namespace App\Http\Controllers;

use App\Models\DemandTemplate;
use App\Repositories\DemandTemplateRepository;
use App\Resources\DemandTemplateResource;
use App\Services\DemandTemplate\DemandTemplateService;
use Newms87\Danx\Http\Controllers\ActionController;

class DemandTemplatesController extends ActionController
{
    public static ?string $repo = DemandTemplateRepository::class;
    public static ?string $resource = DemandTemplateResource::class;

    public function listActive()
    {
        $templates = app(DemandTemplateRepository::class)->getActiveTemplates();
        
        return DemandTemplateResource::collection($templates);
    }

    public function toggleActive(DemandTemplate $demandTemplate)
    {
        $template = app(DemandTemplateService::class)->toggleActive($demandTemplate);
        
        return DemandTemplateResource::make($template);
    }
}