<?php

namespace Tests\Feature\Events;

use App\Events\JobDispatchUpdatedEvent;
use App\Models\Workflow\WorkflowDefinition;
use App\Models\Workflow\WorkflowRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Newms87\Danx\Models\Job\JobDispatch;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class JobDispatchUpdatedEventTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
    }

    public function test_jobDispatch_created_firesEvent(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        $eventFired = false;

        Event::listen(JobDispatchUpdatedEvent::class, function () use (&$eventFired) {
            $eventFired = true;
        });

        // When
        $jobDispatch = JobDispatch::create([
            'ref' => 'test-ref-' . uniqid(),
            'name' => 'Test Job',
            'status' => JobDispatch::STATUS_PENDING,
        ]);

        DB::table('job_dispatchables')->insert([
            'job_dispatch_id' => $jobDispatch->id,
            'model_type' => WorkflowRun::class,
            'model_id' => $workflowRun->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Then
        $this->assertTrue($eventFired, 'JobDispatchUpdatedEvent should fire when JobDispatch is created');
    }

    public function test_teamId_resolvedViaDispatchableRelationship(): void
    {
        // Given
        $workflowDefinition = WorkflowDefinition::factory()->create([
            'team_id' => $this->user->currentTeam->id,
        ]);

        $workflowRun = WorkflowRun::factory()->create([
            'workflow_definition_id' => $workflowDefinition->id,
        ]);

        $jobDispatch = JobDispatch::create([
            'ref' => 'test-ref-' . uniqid(),
            'name' => 'Test Job',
            'status' => JobDispatch::STATUS_PENDING,
        ]);

        DB::table('job_dispatchables')->insert([
            'job_dispatch_id' => $jobDispatch->id,
            'model_type' => WorkflowRun::class,
            'model_id' => $workflowRun->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Subscribe to ensure broadcastOn() returns channels
        $this->postJson('/api/pusher/subscribe', [
            'resource_type' => 'JobDispatch',
            'model_id_or_filter' => true,
        ]);

        // When
        $event = new JobDispatchUpdatedEvent($jobDispatch, 'created');
        $channels = $event->broadcastOn();

        // Then - Should resolve team_id from WorkflowRun through dispatchables
        $this->assertNotEmpty($channels, 'Should broadcast when team_id is resolved');
        $this->assertEquals('private-JobDispatch.' . $this->user->currentTeam->id, $channels[0]->name);
    }
}
