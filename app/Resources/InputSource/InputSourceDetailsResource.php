<?php

namespace App\Resources\InputSource;

use App\Models\Shared\InputSource;
use Flytedan\DanxLaravel\Resources\StoredFileResource;

/**
 * @mixin InputSource
 * @property InputSource $resource
 */
class InputSourceDetailsResource extends InputSourceResource
{
    public function data(): array
    {
        return [
                'files'        => StoredFileResource::collection($this->storedFiles),
                'content'      => $this->content,
                'workflowRuns' => $this->workflowRuns,
            ] + parent::data();
    }
}
