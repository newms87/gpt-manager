<?php

namespace App\Repositories;

use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Models\Audit\AuditRequest;
use Newms87\Danx\Repositories\ActionRepository;

class AuditRequestRepository extends ActionRepository
{
    public static string $model = AuditRequest::class;

    public function applyAction(string $action, $model = null, ?array $data = null)
    {
        throw new ValidationError('Actions are not allowed on Audit Requests');
    }

    public function fieldOptions(?array $filter = []): array
    {
        $urls = $this->query()->distinct()->pluck('url')->toArray();

        return [
            'urls' => $urls,
        ];
    }
}
