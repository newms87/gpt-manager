<?php

namespace App\Http\Controllers;

use App\Repositories\TemplateVariableRepository;
use App\Resources\Template\TemplateVariableResource;
use Newms87\Danx\Http\Controllers\ActionController;

class TemplateVariableController extends ActionController
{
    public static ?string $repo = TemplateVariableRepository::class;

    public static ?string $resource = TemplateVariableResource::class;
}
