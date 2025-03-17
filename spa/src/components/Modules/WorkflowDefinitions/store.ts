import { dxWorkflowDefinition } from "@/components/Modules/WorkflowDefinitions/config";
import { dxWorkflowRun } from "@/components/Modules/WorkflowDefinitions/WorkflowRuns/config";
import { WORKFLOW_STATUS } from "@/components/Modules/WorkflowDefinitions/workflows";
import { TaskDefinition, WorkflowDefinition, WorkflowInput, WorkflowNode, WorkflowRun } from "@/types";
import { autoRefreshObject, getItem, setItem, stopAutoRefreshObject, storeObjects } from "quasar-ui-danx";
import { ref, watch } from "vue";

const ACTIVE_WORKFLOW_DEFINITION_KEY = "dx-active-workflow-definition-id";

const isLoadingWorkflowDefinitions = ref(false);
const activeWorkflowDefinition = ref<WorkflowDefinition>(null);
const activeWorkflowRun = ref<WorkflowRun>(null);
const workflowDefinitions = ref([]);

async function refreshActiveWorkflowDefinition() {
	await dxWorkflowDefinition.routes.details(activeWorkflowDefinition.value);
}

async function setActiveWorkflowDefinition(workflowDefinition: string | number | WorkflowDefinition | null) {
	const workflowDefinitionId = typeof workflowDefinition === "object" ? workflowDefinition?.id : workflowDefinition;
	setItem(ACTIVE_WORKFLOW_DEFINITION_KEY, workflowDefinitionId);

	// Clear active run if the definition has changed
	if (activeWorkflowDefinition.value?.id !== workflowDefinitionId) {
		activeWorkflowRun.value = null;
	}

	activeWorkflowDefinition.value = workflowDefinitions.value.find((tw) => tw.id === workflowDefinitionId) || null;

	if (activeWorkflowDefinition.value) {
		await dxWorkflowDefinition.routes.details(activeWorkflowDefinition.value);
		await loadWorkflowRuns();
	}
}

async function initWorkflowState() {
	await loadWorkflowDefinitions();
	await setActiveWorkflowDefinition(getItem(ACTIVE_WORKFLOW_DEFINITION_KEY));
}

async function loadWorkflowDefinitions() {
	isLoadingWorkflowDefinitions.value = true;
	workflowDefinitions.value = storeObjects((await dxWorkflowDefinition.routes.list()).data);
	isLoadingWorkflowDefinitions.value = false;
}

async function loadWorkflowRuns() {
	if (!activeWorkflowDefinition.value) return;
	return await dxWorkflowDefinition.routes.details(activeWorkflowDefinition.value, { "*": false, runs: true });
}

async function refreshActiveWorkflowRun() {
	if (!activeWorkflowRun.value) return null;
	return await dxWorkflowRun.routes.details(activeWorkflowRun.value, { taskRuns: { taskDefinition: true } });
}

watch(() => activeWorkflowRun.value, autoRefreshWorkflowRun);
async function autoRefreshWorkflowRun() {
	const AUTO_REFRESH_NAME = "active-workflow-run";
	if (activeWorkflowRun.value) {
		await autoRefreshObject(
				AUTO_REFRESH_NAME,
				activeWorkflowRun.value,
				(tr: WorkflowRun) => [WORKFLOW_STATUS.PENDING.value, WORKFLOW_STATUS.RUNNING.value, WORKFLOW_STATUS.DISPATCHED.value].includes(tr.status) || !activeWorkflowRun.value.taskRuns,
				refreshActiveWorkflowRun
		);
	} else {
		stopAutoRefreshObject(AUTO_REFRESH_NAME);
	}
}

const addNodeAction = dxWorkflowDefinition.getAction("add-node", {
	optimistic: (action, target: WorkflowDefinition, data: WorkflowNode) => target.nodes.push({ ...data }),
	onFinish: refreshActiveWorkflowDefinition
});

async function addWorkflowNode(taskDefinition: TaskDefinition, input: Partial<WorkflowNode> = {}) {
	return await addNodeAction.trigger(activeWorkflowDefinition.value, {
		id: "td-" + taskDefinition.id,
		name: taskDefinition.name,
		task_definition_id: taskDefinition.id,
		...input
	});
}

const createWorkflowRunAction = dxWorkflowRun.getAction("quick-create", { onFinish: loadWorkflowRuns });
const isCreatingWorkflowRun = ref(false);
async function createWorkflowRun(workflowInput?: WorkflowInput) {
	if (!activeWorkflowDefinition.value) return;
	activeWorkflowRun.value = null;

	isCreatingWorkflowRun.value = true;
	const result = await createWorkflowRunAction.trigger(null, {
		workflow_definition_id: activeWorkflowDefinition.value.id,
		workflow_input_id: workflowInput?.id
	});
	isCreatingWorkflowRun.value = false;

	activeWorkflowRun.value = result.item;
}

export {
	isLoadingWorkflowDefinitions,
	isCreatingWorkflowRun,
	activeWorkflowDefinition,
	activeWorkflowRun,
	workflowDefinitions,
	initWorkflowState,
	refreshActiveWorkflowDefinition,
	refreshActiveWorkflowRun,
	createWorkflowRun,
	loadWorkflowDefinitions,
	loadWorkflowRuns,
	setActiveWorkflowDefinition,
	addWorkflowNode
};
