<?php

namespace App\Resources\InputSource;

use App\Models\Shared\InputSource;
use Newms87\Danx\Resources\StoredFileWithTranscodesResource;

/**
 * @mixin InputSource
 * @property InputSource $resource
 */
class InputSourceDetailsResource extends InputSourceResource
{
    public function data(): array
    {
        $runs = $this->workflowRuns()->orderByDesc('id')->get();

        return [
                'files'        => StoredFileWithTranscodesResource::collection($this->storedFiles),
                'content'      => $this->content,
                'workflowRuns' => $runs ? InputSourceWorkflowRunDetailsResource::collection($runs) : [],
            ] + parent::data();
    }
}
