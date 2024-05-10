<?php

namespace App\Resources\Agent;

use App\Models\Agent\ThreadRun;
use Flytedan\DanxLaravel\Resources\ActionResource;

/**
 * @mixin ThreadRun
 * @property ThreadRun $resource
 */
class ThreadRunResource extends ActionResource
{
    public static string $type = 'ThreadRun';

    public function data(): array
    {
        return [
            'id'            => $this->id,
            'status'        => $this->status,
            'started_at'    => $this->started_at,
            'completed_at'  => $this->completed_at,
            'failed_at'     => $this->failed_at,
            'refreshed_at'  => $this->refreshed_at,
            'input_token'   => $this->input_tokens,
            'output_tokens' => $this->output_tokens,
        ];
    }
}
