import type { UiDemand, WorkflowConfig } from "@/ui/shared/types";
import type { WorkflowRun } from "@/types";
import { FaSolidCheck, FaSolidClock, FaSolidTriangleExclamation } from "danx-icon";
import { DateTime, fDuration } from "quasar-ui-danx";
import type { ComputedRef, Ref } from "vue";
import { computed } from "vue";
import { DEMAND_STATUS, getWorkflowColors } from "../config";
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
	workflowRuns?: WorkflowRun[];
	extractsData?: boolean;
	config?: WorkflowConfig;
}

/**
 * Get the latest (most recent) workflow run from an array
 * Returns the first element (backend returns sorted by created_at DESC)
 */
const getLatestRun = (runs: WorkflowRun[] | undefined): WorkflowRun | null => {
	if (!runs || runs.length === 0) return null;
	return runs[0];
};

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
	workflowRuns: WorkflowRun[] | undefined,
	demand: UiDemand,
	currentTime: number
): StatusTimelineItem => {
	// Get the latest run for status/display
	const latestRun = getLatestRun(workflowRuns);

	const state = {
		completed: isWorkflowCompleted(latestRun),
		failed: isWorkflowFailed(latestRun),
		active: isWorkflowActive(latestRun),
		stopped: isWorkflowStopped(latestRun)
	};

	// Determine if workflow should be grayed out (dependencies not met)
	let grayed = false;
	if (config.depends_on && config.depends_on.length > 0) {
		// Check if all dependencies are completed using latest run
		grayed = config.depends_on.some((depKey: string) => {
			const depRuns = demand.workflow_runs[depKey];
			const latestDepRun = getLatestRun(depRuns);
			return !isWorkflowCompleted(latestDepRun);
		});
	} else if (config.input?.requires_input_files) {
		// Check if input files exist
		grayed = !demand.input_files || demand.input_files.length === 0;
	}

	// Get icon based on state
	const icon = state.completed ? FaSolidCheck :
		state.failed ? FaSolidTriangleExclamation :
			FaSolidClock;

	// Get colors from the palette system
	const colors = getWorkflowColors(config.color || "slate");
	const failedColors = getWorkflowColors("red");

	const bgColor = state.failed ? failedColors.palette.bgProgress :
		state.completed ? colors.palette.bgProgress :
			state.active ? "bg-slate-200" :
				"bg-gray-400";

	const textColor = state.failed ? failedColors.palette.textOnDark :
		state.completed ? colors.palette.textOnDark :
			"text-gray-200";

	// Get label
	const label = state.failed ? `${config.label} (Failed)` : config.label;

	// Get date
	const date = state.completed ? latestRun?.completed_at :
		state.failed ? latestRun?.failed_at :
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
		progress: state.active ? latestRun?.progress_percent : null,
		date,
		runtime: calculateRuntime(latestRun, state.active, currentTime),
		grayed,
		workflowRun: latestRun,
		workflowRuns: workflowRuns || [],
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
		const workflowRuns = demand.workflow_runs[config.key];
		const latestRun = getLatestRun(workflowRuns);
		return isWorkflowCompleted(latestRun);
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
				const workflowRuns = demand.value.workflow_runs[config.key];
				statuses.push(createWorkflowStatus(config, workflowRuns, demand.value, currentTime.value));
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
