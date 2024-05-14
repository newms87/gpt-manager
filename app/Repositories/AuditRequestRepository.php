<?php

namespace App\Repositories;

use Flytedan\DanxLaravel\Exceptions\ValidationError;
use Flytedan\DanxLaravel\Models\Audit\AuditRequest;
use Flytedan\DanxLaravel\Repositories\ActionRepository;

class AuditRequestRepository extends ActionRepository
{
    public static string $model = AuditRequest::class;

    public function applyAction(string $action, $model = null, ?array $data = null)
    {
        throw new ValidationError("Actions are not allowed on Audit Requests");
    }
}
