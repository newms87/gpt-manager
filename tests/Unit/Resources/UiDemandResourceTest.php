<?php

namespace Tests\Unit\Resources;

use App\Models\Demand\UiDemand;
use App\Models\Usage\UsageEvent;
use App\Resources\UiDemandResource;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class UiDemandResourceTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    public function test_resource_data_method_includes_expected_fields(): void
    {
        $this->setUpTeam();

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $data = UiDemandResource::make($uiDemand);

        $expectedFields = [
            'id',
            'title',
            'description',
            'status',
            'metadata',
            'completed_at',
            'created_at',
            'updated_at',
            'can_extract_data',
            'can_write_demand_letter',
            'is_extract_data_running',
            'is_write_demand_letter_running',
            'usage_summary',
        ];

        foreach($expectedFields as $field) {
            $this->assertArrayHasKey($field, $data, "Field '{$field}' should exist in UiDemandResource");
        }
    }

    public function test_resource_details_method_includes_expected_relationship_fields(): void
    {
        $this->setUpTeam();

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $resource = UiDemandResource::details($uiDemand);

        $expectedRelationshipFields = [
            'user',
            'input_files',
            'output_files',
            'team_object',
            'extract_data_workflow_run',
            'write_demand_letter_workflow_run',
        ];

        foreach($expectedRelationshipFields as $field) {
            $this->assertArrayHasKey($field, $resource, "Relationship field '{$field}' should exist in details response");
        }
    }

    public function test_resource_does_not_include_invalid_usage_events_field(): void
    {
        $this->setUpTeam();

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $resource = UiDemandResource::details($uiDemand);

        $this->assertArrayNotHasKey('usage_events', $resource,
            'usage_events field should not exist in resource - we now use subscription system');
    }

    public function test_usage_summary_field_shows_subscription_based_data(): void
    {
        $this->setUpTeam();

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $usageEvent = UsageEvent::create([
            'team_id'       => $this->user->currentTeam->id,
            'user_id'       => $this->user->id,
            'object_type'   => UiDemand::class,
            'object_id'     => (string)$uiDemand->id,
            'object_id_int' => $uiDemand->id,
            'event_type'    => 'test_event',
            'api_name'      => 'test_api',
            'input_tokens'  => 100,
            'output_tokens' => 50,
            'input_cost'    => 0.001,
            'output_cost'   => 0.002,
            'request_count' => 1,
        ]);

        $uiDemand->subscribeToUsageEvent($usageEvent);
        $uiDemand->refreshUsageSummaryFromSubscribedEvents();
        $uiDemand->refresh();

        $data = UiDemandResource::make($uiDemand);

        $this->assertArrayHasKey('usage_summary', $data);
        $this->assertNotNull($data['usage_summary']);
        $this->assertEquals(0.003, $data['usage_summary']['total_cost']);
        $this->assertEquals(150, $data['usage_summary']['total_tokens']);
    }

    public function test_usage_summary_field_is_null_when_no_usage(): void
    {
        $this->setUpTeam();

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $data = UiDemandResource::make($uiDemand);

        $this->assertArrayHasKey('usage_summary', $data);
        $this->assertNull($data['usage_summary']);
    }

    public function test_resource_make_with_invalid_fields_throws_exception(): void
    {
        $this->setUpTeam();

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('is not a valid field');

        UiDemandResource::make($uiDemand, [
            'invalid_field' => true,
        ]);
    }

    public function test_resource_includes_workflow_run_relationships(): void
    {
        $this->setUpTeam();

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $data = UiDemandResource::make($uiDemand, [
            'extract_data_workflow_run' => true,
            'write_demand_letter_workflow_run' => true,
        ]);

        $this->assertArrayHasKey('extract_data_workflow_run', $data);
        $this->assertArrayHasKey('write_demand_letter_workflow_run', $data);
    }

    public function test_resource_conditionally_loads_relationships(): void
    {
        $this->setUpTeam();

        $uiDemand = UiDemand::factory()->create([
            'team_id' => $this->user->currentTeam->id,
            'user_id' => $this->user->id,
        ]);

        $dataWithUser = UiDemandResource::make($uiDemand, ['user' => true]);
        $this->assertArrayHasKey('user', $dataWithUser);

        $dataWithoutUser = UiDemandResource::make($uiDemand);
        $this->assertArrayNotHasKey('user', $dataWithoutUser);
    }
}
