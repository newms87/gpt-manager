<?php

namespace App\WorkflowTools\Traits;

use App\Models\Agent\Thread;
use App\Models\Workflow\WorkflowJobRun;
use App\Models\Workflow\WorkflowRun;
use App\Models\Workflow\WorkflowTask;
use App\Repositories\ThreadRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

trait AssignsWorkflowTasksTrait
{
	/**
	 * Assign tasks to the workflow job run based on the assignments and the dependency artifacts
	 * (ie: tuples of WorkflowJobRun Artifacts that should be passed to each assignment)
	 */
	public function assignTasks(WorkflowJobRun $workflowJobRun, array|Collection $dependencyArtifacts = []): void
	{
		$assignments = $workflowJobRun->workflowJob->workflowAssignments()->get();

		if ($assignments->isEmpty()) {
			Log::debug("$workflowJobRun the workflow job has no assignments, skipping task creation");

			return;
		}

		// Make sure we always assign at least 1 default task even if no artifacts were passed / no input required
		if (!$dependencyArtifacts) {
			$dependencyArtifacts = [
				'default' => [
					['content' => 'Follow the prompt'],
				],
			];
		}

		$this->assignTasksByDependencyArtifacts($workflowJobRun, $assignments, $dependencyArtifacts);
	}

	/**
	 * Assign tasks to each assignment based on the artifacts in each group of dependencyArtifacts.
	 * Each group is a tuple that represents a unique set of artifacts to be used in a single task
	 */
	public function assignTasksByDependencyArtifacts(WorkflowJobRun $workflowJobRun, Collection $assignments, array $dependencyArtifacts): void
	{
		foreach($assignments as $assignment) {
			foreach($dependencyArtifacts as $groupName => $artifactTuple) {
				$task = $workflowJobRun->tasks()->create([
					'user_id'                => user()->id,
					'workflow_job_id'        => $workflowJobRun->workflow_job_id,
					'workflow_assignment_id' => $assignment->id,
					'group'                  => $groupName,
					'status'                 => WorkflowRun::STATUS_PENDING,
				]);

				$this->setupTaskThread($task, $artifactTuple);

				Log::debug("$workflowJobRun created $task for $assignment with group $groupName");
			}
		}
	}

	/**
	 * Create a thread for the task and add messages from the workflow input or dependencies
	 */
	protected function setupTaskThread(WorkflowTask $workflowTask, array $artifactTuple): Thread
	{
		$assignment  = $workflowTask->workflowAssignment;
		$workflowJob = $workflowTask->workflowJob;

		$threadName = $workflowJob->name . " ($workflowTask->id) [group: " . ($workflowTask->group ?: 'default') . "] by {$assignment->agent->name}";
		$thread     = app(ThreadRepository::class)->create($assignment->agent, $threadName);

		Log::debug("Setup Task Thread: $workflowTask created $thread");

		Log::debug("\tAdding " . count($artifactTuple) . " artifacts");
		foreach($artifactTuple as $item) {
			app(ThreadRepository::class)->addMessageToThread($thread, $item);
		}

		$workflowTask->thread()->associate($thread)->save();

		return $thread;
	}
}
