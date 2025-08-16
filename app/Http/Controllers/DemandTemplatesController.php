<?php

namespace App\Http\Controllers;

use App\Repositories\DemandTemplateRepository;
use App\Resources\DemandTemplateResource;
use Newms87\Danx\Http\Controllers\ActionController;

class DemandTemplatesController extends ActionController
{
    public static ?string $repo     = DemandTemplateRepository::class;
    public static ?string $resource = DemandTemplateResource::class;
}
