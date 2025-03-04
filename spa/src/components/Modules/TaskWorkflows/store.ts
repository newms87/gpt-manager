import { dxTaskWorkflow } from "@/components/Modules/TaskWorkflows/config";
import { TaskWorkflow, TaskWorkflowRun } from "@/types/task-workflows";
import { getItem, setItem, storeObjects } from "quasar-ui-danx";
import { ref } from "vue";

const ACTIVE_TASK_WORKFLOW_KEY = "dx-active-task-workflow-id";

const isLoadingWorkflows = ref(false);
const activeTaskWorkflow = ref<TaskWorkflow>(null);
const activeTaskWorkflowRun = ref<TaskWorkflowRun>(null);
const taskWorkflows = ref([]);

async function refreshActiveTaskWorkflow() {
	await dxTaskWorkflow.routes.details(activeTaskWorkflow.value);
}

async function setActiveTaskWorkflow(taskWorkflow: string | number | TaskWorkflow | null) {
	const taskWorkflowId = typeof taskWorkflow === "object" ? taskWorkflow.id : taskWorkflow;
	setItem(ACTIVE_TASK_WORKFLOW_KEY, taskWorkflowId);

	activeTaskWorkflow.value = taskWorkflows.value.find((tw) => tw.id === taskWorkflowId) || null;
	if (activeTaskWorkflow.value) {
		await dxTaskWorkflow.routes.details(activeTaskWorkflow.value);
		await loadTaskWorkflowRuns();
	}
}

async function initWorkflowState() {
	await loadTaskWorkflows();
	await setActiveTaskWorkflow(getItem(ACTIVE_TASK_WORKFLOW_KEY));
}

async function loadTaskWorkflows() {
	isLoadingWorkflows.value = true;
	taskWorkflows.value = storeObjects((await dxTaskWorkflow.routes.list()).data);
	isLoadingWorkflows.value = false;
}

async function loadTaskWorkflowRuns() {
	await dxTaskWorkflow.routes.details(activeTaskWorkflow.value, { "*": false, runs: { taskRuns: true } });
}

export {
	isLoadingWorkflows,
	activeTaskWorkflow,
	activeTaskWorkflowRun,
	taskWorkflows,
	initWorkflowState,
	refreshActiveTaskWorkflow,
	loadTaskWorkflows,
	loadTaskWorkflowRuns,
	setActiveTaskWorkflow
};
