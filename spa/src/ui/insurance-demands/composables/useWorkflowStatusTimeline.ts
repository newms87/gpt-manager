import type { UiDemand, WorkflowConfig } from "@/ui/shared/types";
import type { WorkflowRun } from "@/types";
import { FaSolidCheck, FaSolidClock, FaSolidTriangleExclamation } from "danx-icon";
import { DateTime, fDuration } from "quasar-ui-danx";
import type { ComputedRef, Ref } from "vue";
import { computed } from "vue";
import { DEMAND_STATUS } from "../config";
import { isWorkflowActive, isWorkflowCompleted, isWorkflowFailed, isWorkflowStopped } from "./useWorkflowState";

/**
 * Status timeline item interface
 */
export interface StatusTimelineItem {
	name: string;
	label: string;
	icon: any;
	bgColor: string;
	textColor: string;
	completed: boolean;
	failed: boolean;
	isActive: boolean;
	isStopped: boolean;
	progress: number | null;
	date: string | null;
	runtime: string | null;
	grayed: boolean;
	workflowRun: WorkflowRun | null;
	extractsData?: boolean;
	config?: WorkflowConfig;
}

/**
 * Calculate runtime for a workflow run using fDuration
 */
const calculateRuntime = (workflowRun: WorkflowRun | null, isActive: boolean, currentTime: number): string | null => {
	if (!workflowRun?.started_at) return null;

	let endTime: string | DateTime;

	if (isActive) {
		// For running workflows, use reactive currentTime for live updates
		endTime = DateTime.fromMillis(currentTime);
	} else {
		// For completed/failed workflows, use completed_at or failed_at
		const completedAt = workflowRun.completed_at || workflowRun.failed_at;
		if (!completedAt) return null;
		endTime = completedAt;
	}

	return fDuration(workflowRun.started_at, endTime);
};

/**
 * Create the "Created (Draft)" status
 */
const createDraftStatus = (demand: UiDemand): StatusTimelineItem => {
	return {
		name: "draft",
		label: "Created (Draft)",
		icon: FaSolidClock,
		bgColor: "bg-slate-500",
		textColor: "text-slate-100",
		completed: true,
		failed: false,
		isActive: false,
		isStopped: false,
		progress: null,
		date: demand.created_at,
		runtime: null,
		grayed: false,
		workflowRun: null
	};
};

/**
 * Create a workflow status item
 */
const createWorkflowStatus = (
	config: WorkflowConfig,
	workflowRun: WorkflowRun | null,
	demand: UiDemand,
	currentTime: number
): StatusTimelineItem => {
	const state = {
		completed: isWorkflowCompleted(workflowRun),
		failed: isWorkflowFailed(workflowRun),
		active: isWorkflowActive(workflowRun),
		stopped: isWorkflowStopped(workflowRun)
	};

	// Determine if workflow should be grayed out (dependencies not met)
	let grayed = false;
	if (config.depends_on && config.depends_on.length > 0) {
		// Check if all dependencies are completed
		grayed = config.depends_on.some((depKey: string) => {
			const depRun = demand.workflow_runs[depKey];
			return !isWorkflowCompleted(depRun);
		});
	} else if (config.input?.requires_input_files) {
		// Check if input files exist
		grayed = !demand.input_files || demand.input_files.length === 0;
	}

	// Get icon based on state
	const icon = state.completed ? FaSolidCheck :
		state.failed ? FaSolidTriangleExclamation :
			FaSolidClock;

	// Get colors from config (Tailwind color names like "blue", "teal", "green")
	const colorName = config.color || "gray";
	const bgColor = state.failed ? "bg-red-500" :
		state.completed ? `bg-${colorName}-500` :
			state.active ? "bg-slate-200" :
				"bg-gray-400";

	const textColor = state.failed ? "text-red-200" :
		state.completed ? `text-${colorName}-200` :
			"text-gray-200";

	// Get label
	const label = state.failed ? `${config.label} (Failed)` : config.label;

	// Get date
	const date = state.completed ? workflowRun?.completed_at :
		state.failed ? workflowRun?.failed_at :
			null;

	return {
		name: config.key,
		label,
		icon,
		bgColor,
		textColor,
		completed: state.completed,
		failed: state.failed,
		isActive: state.active,
		isStopped: state.stopped,
		progress: state.active ? workflowRun?.progress_percent : null,
		date,
		runtime: calculateRuntime(workflowRun, state.active, currentTime),
		grayed,
		workflowRun,
		extractsData: config.extracts_data, // Used for "View Data" button
		config // Include the workflow config for run button logic
	};
};

/**
 * Create the "Complete" status
 */
const createCompleteStatus = (demand: UiDemand): StatusTimelineItem => {
	// Determine if complete status should be grayed (all workflows not done)
	const allWorkflowsComplete = demand.workflow_config?.every(config => {
		const workflowRun = demand.workflow_runs[config.key];
		return isWorkflowCompleted(workflowRun);
	}) ?? false;

	return {
		name: "completed",
		label: "Complete",
		icon: FaSolidCheck,
		bgColor: demand.status === DEMAND_STATUS.COMPLETED ? "bg-green-600" : "bg-gray-400",
		textColor: demand.status === DEMAND_STATUS.COMPLETED ? "text-green-200" : "text-gray-200",
		completed: demand.status === DEMAND_STATUS.COMPLETED,
		failed: false,
		isActive: false,
		isStopped: false,
		progress: null,
		date: demand.completed_at,
		runtime: null,
		grayed: !allWorkflowsComplete,
		workflowRun: null
	};
};

/**
 * Composable for managing workflow status timeline
 *
 * @param demand - Reactive reference to the UiDemand object
 * @param currentTime - Reactive reference to current timestamp for live runtime updates
 * @returns statusTimeline - Computed array of status timeline items
 */
export function useWorkflowStatusTimeline(
	demand: Ref<UiDemand | null>,
	currentTime: Ref<number>
) {
	const statusTimeline: ComputedRef<StatusTimelineItem[]> = computed(() => {
		if (!demand.value) return [];

		// Start with "Created (Draft)" status
		const statuses = [createDraftStatus(demand.value)];

		// Add status for each workflow from config
		if (demand.value.workflow_config) {
			for (const config of demand.value.workflow_config) {
				const workflowRun = demand.value.workflow_runs[config.key];
				statuses.push(createWorkflowStatus(config, workflowRun, demand.value, currentTime.value));
			}
		}

		// Add "Complete" status
		statuses.push(createCompleteStatus(demand.value));

		return statuses;
	});

	return {
		statusTimeline
	};
}
