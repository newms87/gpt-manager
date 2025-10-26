<?php

namespace App\Api\WorkflowApiInvocationWebhook;

use App\Models\Workflow\WorkflowApiInvocation;
use App\Models\Workflow\WorkflowRun;
use App\Models\Workflow\WorkflowStatesContract;
use App\Resources\Workflow\WebhookArtifactResource;
use Newms87\Danx\Api\Api;

class WorkflowApInvocationWebhook extends Api
{
    public function __construct()
    {
        $this->baseApiUrl = '';
    }

    public function callWebhook(WorkflowApiInvocation $workflowApiInvocation): void
    {
        if (!$workflowApiInvocation->webhook_url) {
            return;
        }

        $output      = null;
        $workflowRun = $workflowApiInvocation->workflowRun;

        if ($workflowRun->status === WorkflowStatesContract::STATUS_COMPLETED) {
            $output = $this->renderOutput($workflowRun);
        }

        $this->post($workflowApiInvocation->webhook_url, [
            'payload' => $workflowApiInvocation->payload,
            'status'  => $workflowRun->status,
            'output'  => $output,
        ]);
    }

    public function renderOutput(WorkflowRun $workflowRun): array
    {
        $artifacts = $workflowRun->collectFinalOutputArtifacts();

        $output = [];

        foreach ($artifacts as $artifact) {
            $output[] = WebhookArtifactResource::make($artifact);
        }

        return $output;
    }
}
