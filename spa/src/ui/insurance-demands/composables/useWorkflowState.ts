import type { WorkflowRun } from "@/types";

/**
 * Shared composable for workflow state management
 * Provides consistent workflow state checks across all components
 * Uses workflow_run.status as the single source of truth
 */

export type TaskRunStatus =
	| "Pending"
	| "Running"
	| "Incomplete"
	| "Stopped"
	| "Failed"
	| "Skipped"
	| "Completed";

/**
 * Check if a workflow is currently active (running)
 * Active states: Pending, Running, Incomplete
 */
export function isWorkflowActive(workflowRun?: WorkflowRun | null): boolean {
	if (!workflowRun?.status) return false;
	return ["Pending", "Running", "Incomplete"].includes(workflowRun.status);
}

/**
 * Check if a workflow has completed successfully
 */
export function isWorkflowCompleted(workflowRun?: WorkflowRun | null): boolean {
	if (!workflowRun?.status) return false;
	return ["Completed", "Skipped"].includes(workflowRun.status);
}

/**
 * Check if a workflow has failed
 */
export function isWorkflowFailed(workflowRun?: WorkflowRun | null): boolean {
	if (!workflowRun?.status) return false;
	return workflowRun.status === "Failed";
}

/**
 * Check if a workflow has been stopped
 */
export function isWorkflowStopped(workflowRun?: WorkflowRun | null): boolean {
	if (!workflowRun?.status) return false;
	return workflowRun.status === "Stopped";
}

/**
 * Helper to get workflow state object (similar to DemandStatusTimeline logic)
 */
export function getWorkflowState(workflowRun?: WorkflowRun | null) {
	return {
		active: isWorkflowActive(workflowRun),
		completed: isWorkflowCompleted(workflowRun),
		failed: isWorkflowFailed(workflowRun),
		stopped: isWorkflowStopped(workflowRun),
		exists: !!workflowRun
	};
}

export function useWorkflowState() {
	return {
		isWorkflowActive,
		isWorkflowCompleted,
		isWorkflowFailed,
		isWorkflowStopped,
		getWorkflowState
	};
}
