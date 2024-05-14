<?php

namespace App\Resources\Audit;

use Flytedan\DanxLaravel\Models\Audit\AuditRequest;

/**
 * @mixin AuditRequest
 * @property AuditRequest $resource
 */
class AuditRequestDetailsResource extends AuditRequestResource
{
    public function data(): array
    {
        return [
                'audits' => AuditResource::collection($this->audits),
            ] + parent::data();
    }
}
