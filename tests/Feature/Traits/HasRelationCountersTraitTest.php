<?php

namespace Feature\Traits;

use App\Models\Agent\Agent;
use App\Models\User;
use App\Models\Workflow\Artifact;
use App\Models\Workflow\WorkflowRun;
use Tests\TestCase;

class HasRelationCountersTraitTest extends TestCase
{
    public function test_syncRelatedModels_countsCreateEvent(): void
    {
        // Given
        $agent = Agent::factory()->create();
        $user  = User::factory()->create();

        // When
        $agent->threads()->create([
            'team_id' => $agent->team_id,
            'user_id' => $user->id,
            'name'    => 'AgentThread 1',
        ]);

        // Then
        $agent->refresh();
        $this->assertEquals(1, $agent->threads_count);
    }

    public function test_syncRelatedModels_countsMultipleCreateEvents(): void
    {
        // Given
        $agent      = Agent::factory()->create();
        $user       = User::factory()->create();
        $threadData = [
            'team_id' => $agent->team_id,
            'user_id' => $user->id,
        ];

        // When
        $agent->threads()->create(['name' => 'AgentThread 1'] + $threadData);
        $agent->threads()->create(['name' => 'AgentThread 2'] + $threadData);
        $agent->threads()->create(['name' => 'AgentThread 3'] + $threadData);

        // Then
        $agent->refresh();
        $this->assertEquals(3, $agent->threads_count);
    }

    public function test_syncRelatedModels_countsCreateAndDeleteEvents(): void
    {
        // Given
        $agent      = Agent::factory()->create();
        $user       = User::factory()->create();
        $threadData = [
            'team_id' => $agent->team_id,
            'user_id' => $user->id,
        ];

        // When
        $thread = $agent->threads()->create(['name' => 'AgentThread 1'] + $threadData);
        $thread->delete();

        // Then
        $agent->refresh();
        $this->assertEquals(0, $agent->threads_count);
    }

    public function test_syncRelatedModels_countsMultipleCreateAndDeleteEvents(): void
    {
        // Given
        $agent      = Agent::factory()->create();
        $user       = User::factory()->create();
        $threadData = [
            'team_id' => $agent->team_id,
            'user_id' => $user->id,
        ];

        // When
        $thread1 = $agent->threads()->create(['name' => 'AgentThread 1'] + $threadData);
        $thread2 = $agent->threads()->create(['name' => 'AgentThread 2'] + $threadData);
        $thread3 = $agent->threads()->create(['name' => 'AgentThread 3'] + $threadData);
        $thread2->delete();
        $thread1->delete();

        // Then
        $agent->refresh();
        $this->assertEquals(1, $agent->threads_count);
    }

    public function test_syncRelatedModels_countsCreateEventForMorphPivotRelation(): void
    {
        // Given
        $wr       = WorkflowRun::factory()->create();
        $artifact = Artifact::factory()->create();

        // When
        $wr->artifacts()->syncWithoutDetaching([$artifact->id]);

        // Then
        $wr->refresh();
        $this->assertEquals(1, $wr->artifacts_count);
    }
}
