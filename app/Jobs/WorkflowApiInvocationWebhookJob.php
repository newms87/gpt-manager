<?php

namespace App\Jobs;

use App\Api\WorkflowApiInvocationWebhook\WorkflowApInvocationWebhook;
use App\Models\Workflow\WorkflowApiInvocation;
use Newms87\Danx\Jobs\Job;

class WorkflowApiInvocationWebhookJob extends Job
{
    public WorkflowApiInvocation $workflowApiInvocation;

    public function __construct(WorkflowApiInvocation $workflowApiInvocation)
    {
        $this->workflowApiInvocation = $workflowApiInvocation;
        parent::__construct();
    }

    public function ref(): string
    {
        return 'workflow-api-invocation-webhook:' . $this->workflowApiInvocation->id;
    }

    public function run(): void
    {
        // Make a post request to the URL passing the payload and the workflow status as JSON post body:
        app(WorkflowApInvocationWebhook::class)->callWebhook($this->workflowApiInvocation);
    }
}
