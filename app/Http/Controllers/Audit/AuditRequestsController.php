<?php

namespace App\Http\Controllers\Audit;

use App\Repositories\AuditRequestRepository;
use App\Resources\Audit\AuditRequestDetailsResource;
use App\Resources\Audit\AuditRequestResource;
use Flytedan\DanxLaravel\Http\Controllers\ActionController;

class AuditRequestsController extends ActionController
{
    public static string  $repo            = AuditRequestRepository::class;
    public static ?string $resource        = AuditRequestResource::class;
    public static ?string $detailsResource = AuditRequestDetailsResource::class;
}
