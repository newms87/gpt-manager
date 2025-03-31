<?php

namespace App\Resources\Workflow;

use App\Models\Task\Artifact;
use Newms87\Danx\Resources\ActionResource;

class WebhookArtifactResource extends ActionResource
{
    public static function data(Artifact $artifact): array
    {
        return [
            'name'         => $artifact->name,
            'text_content' => $artifact->text_content,
            'json_content' => $artifact->canView() ? $artifact->json_content : [],
            'files'        => WebhookStoredFileResource::collection($artifact->storedFiles),
        ];
    }
}
