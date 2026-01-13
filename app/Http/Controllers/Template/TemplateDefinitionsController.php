<?php

namespace App\Http\Controllers\Template;

use App\Repositories\TemplateDefinitionRepository;
use App\Resources\Template\TemplateDefinitionResource;
use Newms87\Danx\Http\Controllers\ActionController;

class TemplateDefinitionsController extends ActionController
{
    public static ?string $repo = TemplateDefinitionRepository::class;

    public static ?string $resource = TemplateDefinitionResource::class;
}
