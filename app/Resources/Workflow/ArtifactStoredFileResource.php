<?php

namespace App\Resources\Workflow;

use Newms87\Danx\Models\Utilities\StoredFile;
use Newms87\Danx\Resources\ActionResource;

class ArtifactStoredFileResource extends ActionResource
{
    public static bool $withTypedData = false;

    public static function data(StoredFile $storedFile): array
    {
        return [
            'id'          => $storedFile->id,
            'filename'    => $storedFile->filename,
            'url'         => $storedFile->url,
            'page_number' => $storedFile->page_number,
            'size'        => $storedFile->size,
            'mime'        => $storedFile->mime,
        ];
    }
}
