<?php

namespace App\Http\Controllers\Audit;

use App\Repositories\AuditRequestRepository;
use App\Resources\Audit\AuditRequestResource;
use Newms87\Danx\Http\Controllers\ActionController;

class AuditRequestsController extends ActionController
{
    public static ?string $repo     = AuditRequestRepository::class;

    public static ?string $resource = AuditRequestResource::class;
}
