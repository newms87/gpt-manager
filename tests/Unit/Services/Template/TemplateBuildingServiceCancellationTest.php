<?php

namespace Tests\Unit\Services\Template;

use App\Models\Template\TemplateDefinition;
use App\Services\Template\TemplateBuildingService;
use Newms87\Danx\Models\Job\JobDispatch;
use ReflectionMethod;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class TemplateBuildingServiceCancellationTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    protected TemplateBuildingService $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
        $this->service = app(TemplateBuildingService::class);
    }

    /**
     * Create a JobDispatch for testing purposes.
     */
    protected function createJobDispatch(string $status = JobDispatch::STATUS_RUNNING): JobDispatch
    {
        return JobDispatch::create([
            'ref'    => 'test-build-job-' . uniqid(),
            'name'   => 'TemplateBuildingJob',
            'status' => $status,
        ]);
    }

    /**
     * Call the protected checkCancellation method using reflection.
     */
    protected function callCheckCancellation(TemplateDefinition $template): bool
    {
        $method = new ReflectionMethod(TemplateBuildingService::class, 'checkCancellation');

        return $method->invoke($this->service, $template);
    }

    // ==========================================
    // CHECK CANCELLATION TESTS
    // ==========================================

    public function test_check_cancellation_returns_false_when_job_is_running(): void
    {
        // Given a template with a running job dispatch
        $jobDispatch = $this->createJobDispatch(JobDispatch::STATUS_RUNNING);
        $template    = TemplateDefinition::factory()->create([
            'team_id'                  => $this->user->currentTeam->id,
            'user_id'                  => $this->user->id,
            'building_job_dispatch_id' => $jobDispatch->id,
        ]);

        // When we check for cancellation
        $result = $this->callCheckCancellation($template);

        // Then it should return false (build should continue)
        $this->assertFalse($result);
    }

    public function test_check_cancellation_returns_true_when_job_is_aborted(): void
    {
        // Given a template with an aborted job dispatch
        $jobDispatch = $this->createJobDispatch(JobDispatch::STATUS_ABORTED);
        $template    = TemplateDefinition::factory()->create([
            'team_id'                  => $this->user->currentTeam->id,
            'user_id'                  => $this->user->id,
            'building_job_dispatch_id' => $jobDispatch->id,
        ]);

        // When we check for cancellation
        $result = $this->callCheckCancellation($template);

        // Then it should return true (build should abort)
        $this->assertTrue($result);
    }

    public function test_check_cancellation_clears_building_job_dispatch_id_when_aborted(): void
    {
        // Given a template with an aborted job dispatch
        $jobDispatch = $this->createJobDispatch(JobDispatch::STATUS_ABORTED);
        $template    = TemplateDefinition::factory()->create([
            'team_id'                  => $this->user->currentTeam->id,
            'user_id'                  => $this->user->id,
            'building_job_dispatch_id' => $jobDispatch->id,
        ]);

        // When we check for cancellation
        $this->callCheckCancellation($template);

        // Then the building_job_dispatch_id should be cleared
        $template->refresh();
        $this->assertNull($template->building_job_dispatch_id);
    }

    public function test_check_cancellation_refreshes_job_dispatch_from_database(): void
    {
        // Given a template with a running job dispatch
        $jobDispatch = $this->createJobDispatch(JobDispatch::STATUS_RUNNING);
        $template    = TemplateDefinition::factory()->create([
            'team_id'                  => $this->user->currentTeam->id,
            'user_id'                  => $this->user->id,
            'building_job_dispatch_id' => $jobDispatch->id,
        ]);

        // Simulate another process aborting the job (directly in database)
        JobDispatch::where('id', $jobDispatch->id)->update(['status' => JobDispatch::STATUS_ABORTED]);

        // When we check for cancellation (should refresh from DB)
        $result = $this->callCheckCancellation($template);

        // Then it should detect the aborted status from the database
        $this->assertTrue($result);
    }

    public function test_check_cancellation_saves_cleared_state_to_database(): void
    {
        // Given a template with an aborted job dispatch
        $jobDispatch = $this->createJobDispatch(JobDispatch::STATUS_ABORTED);
        $template    = TemplateDefinition::factory()->create([
            'team_id'                  => $this->user->currentTeam->id,
            'user_id'                  => $this->user->id,
            'building_job_dispatch_id' => $jobDispatch->id,
        ]);

        // When we check for cancellation
        $this->callCheckCancellation($template);

        // Then the database should reflect the cleared state
        $this->assertDatabaseHas('template_definitions', [
            'id'                       => $template->id,
            'building_job_dispatch_id' => null,
        ]);
    }

    public function test_check_cancellation_does_not_modify_running_template(): void
    {
        // Given a template with a running job dispatch
        $jobDispatch = $this->createJobDispatch(JobDispatch::STATUS_RUNNING);
        $template    = TemplateDefinition::factory()->create([
            'team_id'                  => $this->user->currentTeam->id,
            'user_id'                  => $this->user->id,
            'building_job_dispatch_id' => $jobDispatch->id,
        ]);

        // When we check for cancellation
        $this->callCheckCancellation($template);

        // Then the template state should remain unchanged
        $this->assertDatabaseHas('template_definitions', [
            'id'                       => $template->id,
            'building_job_dispatch_id' => $jobDispatch->id,
        ]);
    }

    public function test_check_cancellation_returns_false_when_no_job_dispatch(): void
    {
        // Given a template without a job dispatch
        $template = TemplateDefinition::factory()->create([
            'team_id'                  => $this->user->currentTeam->id,
            'user_id'                  => $this->user->id,
            'building_job_dispatch_id' => null,
        ]);

        // When we check for cancellation
        $result = $this->callCheckCancellation($template);

        // Then it should return false (no job to check)
        $this->assertFalse($result);
    }
}
