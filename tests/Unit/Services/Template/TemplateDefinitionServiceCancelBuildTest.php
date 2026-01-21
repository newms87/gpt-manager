<?php

namespace Tests\Unit\Services\Template;

use App\Models\Template\TemplateDefinition;
use App\Services\Template\TemplateDefinitionService;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Models\Job\JobDispatch;
use Tests\AuthenticatedTestCase;
use Tests\Traits\SetUpTeamTrait;

class TemplateDefinitionServiceCancelBuildTest extends AuthenticatedTestCase
{
    use SetUpTeamTrait;

    protected TemplateDefinitionService $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpTeam();
        $this->service = app(TemplateDefinitionService::class);
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

    // ==========================================
    // CANCEL BUILD TESTS
    // ==========================================

    public function test_cancel_build_sets_job_dispatch_to_aborted(): void
    {
        // Given a template with a build in progress
        $jobDispatch = $this->createJobDispatch(JobDispatch::STATUS_RUNNING);
        $template    = TemplateDefinition::factory()->create([
            'team_id'                  => $this->user->currentTeam->id,
            'user_id'                  => $this->user->id,
            'building_job_dispatch_id' => $jobDispatch->id,
        ]);

        // When we cancel the build
        $this->service->cancelBuild($template);

        // Then the job dispatch status should be set to aborted
        $jobDispatch->refresh();
        $this->assertEquals(JobDispatch::STATUS_ABORTED, $jobDispatch->status);
    }

    public function test_cancel_build_throws_when_no_build_in_progress(): void
    {
        // Given a template without a build in progress
        $template = TemplateDefinition::factory()->create([
            'team_id'                  => $this->user->currentTeam->id,
            'user_id'                  => $this->user->id,
            'building_job_dispatch_id' => null,
        ]);

        // Expect
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('No build in progress to cancel');

        // When
        $this->service->cancelBuild($template);
    }

    public function test_cancel_build_preserves_pending_context_by_default(): void
    {
        // Given a template with a build in progress and pending contexts
        $jobDispatch    = $this->createJobDispatch(JobDispatch::STATUS_RUNNING);
        $pendingContext = ['Build a header section', 'Add footer with contact info'];
        $template       = TemplateDefinition::factory()->create([
            'team_id'                  => $this->user->currentTeam->id,
            'user_id'                  => $this->user->id,
            'building_job_dispatch_id' => $jobDispatch->id,
            'pending_build_context'    => $pendingContext,
        ]);

        // When we cancel the build without discarding pending
        $this->service->cancelBuild($template, discardPending: false);

        // Then the pending context should be preserved
        $template->refresh();
        $this->assertEquals($pendingContext, $template->pending_build_context);
    }

    public function test_cancel_build_discards_pending_context_when_requested(): void
    {
        // Given a template with a build in progress and pending contexts
        $jobDispatch    = $this->createJobDispatch(JobDispatch::STATUS_RUNNING);
        $pendingContext = ['Build a header section', 'Add footer with contact info'];
        $template       = TemplateDefinition::factory()->create([
            'team_id'                  => $this->user->currentTeam->id,
            'user_id'                  => $this->user->id,
            'building_job_dispatch_id' => $jobDispatch->id,
            'pending_build_context'    => $pendingContext,
        ]);

        // When we cancel the build with discard pending flag
        $this->service->cancelBuild($template, discardPending: true);

        // Then the pending context should be cleared
        $template->refresh();
        $this->assertNull($template->pending_build_context);
    }

    public function test_cancel_build_returns_updated_template(): void
    {
        // Given a template with a build in progress
        $jobDispatch = $this->createJobDispatch(JobDispatch::STATUS_RUNNING);
        $template    = TemplateDefinition::factory()->create([
            'team_id'                  => $this->user->currentTeam->id,
            'user_id'                  => $this->user->id,
            'building_job_dispatch_id' => $jobDispatch->id,
        ]);

        // When we cancel the build
        $result = $this->service->cancelBuild($template);

        // Then the returned template should be the same instance
        $this->assertInstanceOf(TemplateDefinition::class, $result);
        $this->assertEquals($template->id, $result->id);
    }

    public function test_cancel_build_saves_job_dispatch_status_to_database(): void
    {
        // Given a template with a build in progress
        $jobDispatch = $this->createJobDispatch(JobDispatch::STATUS_RUNNING);
        $template    = TemplateDefinition::factory()->create([
            'team_id'                  => $this->user->currentTeam->id,
            'user_id'                  => $this->user->id,
            'building_job_dispatch_id' => $jobDispatch->id,
        ]);

        // When we cancel the build
        $this->service->cancelBuild($template);

        // Then the database should reflect the aborted status
        $this->assertDatabaseHas('job_dispatch', [
            'id'     => $jobDispatch->id,
            'status' => JobDispatch::STATUS_ABORTED,
        ]);
    }

    public function test_cancel_build_with_discard_pending_clears_database_value(): void
    {
        // Given a template with pending context
        $jobDispatch = $this->createJobDispatch(JobDispatch::STATUS_RUNNING);
        $template    = TemplateDefinition::factory()->create([
            'team_id'                  => $this->user->currentTeam->id,
            'user_id'                  => $this->user->id,
            'building_job_dispatch_id' => $jobDispatch->id,
            'pending_build_context'    => ['Some pending request'],
        ]);

        // When we cancel with discard flag
        $this->service->cancelBuild($template, discardPending: true);

        // Then the database should show null pending context
        $freshTemplate = TemplateDefinition::find($template->id);
        $this->assertNull($freshTemplate->pending_build_context);
    }
}
