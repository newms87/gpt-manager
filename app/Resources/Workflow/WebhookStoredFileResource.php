<?php

namespace App\Resources\Workflow;

use Newms87\Danx\Models\Utilities\StoredFile;
use Newms87\Danx\Resources\ActionResource;

class WebhookStoredFileResource extends ActionResource
{
    public static function data(StoredFile $storedFile): array
    {
        return [
            'filename' => $storedFile->filename,
            'url'      => $storedFile->url,
            'size'     => $storedFile->size,
            'mime'     => $storedFile->mime,
        ];
    }
}
