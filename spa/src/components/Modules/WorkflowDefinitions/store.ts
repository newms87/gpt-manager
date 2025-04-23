import { dxWorkflowDefinition } from "@/components/Modules/WorkflowDefinitions/config";
import { dxWorkflowRun } from "@/components/Modules/WorkflowDefinitions/WorkflowRuns/config";
import { usePusher } from "@/helpers/pusher";
import {
	TaskDefinition,
	TaskRun,
	TaskRunnerClass,
	WorkflowDefinition,
	WorkflowInput,
	WorkflowNode,
	WorkflowRun
} from "@/types";
import { getItem, setItem, storeObjects } from "quasar-ui-danx";
import { ref } from "vue";

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

async function refreshWorkflowRun(workflowRun: WorkflowRun) {
	return await dxWorkflowRun.routes.details(workflowRun, { taskRuns: { taskDefinition: true } });
}

/**
 *  Whenever a task run has been created for the workflow run, refresh the workflow to get the latest taskRuns list
 */
usePusher().subscribe("TaskRun", "created", async (taskRun: TaskRun) => {
	if (taskRun.workflow_run_id === activeWorkflowRun.value?.id) {
		await refreshWorkflowRun(activeWorkflowRun.value);
	}
});

const addNodeAction = dxWorkflowDefinition.getAction("add-node", {
	optimistic: (action, target: WorkflowDefinition, data: WorkflowNode) => target.nodes.push({ ...data }),
	onFinish: async () => await refreshWorkflowRun(activeWorkflowRun.value)
});

async function addWorkflowNode(newNode: TaskDefinition | TaskRunnerClass, input: Partial<WorkflowNode> = {}) {
	return await addNodeAction.trigger(activeWorkflowDefinition.value, {
		id: "td-" + newNode.name,
		name: newNode.name,
		task_definition_id: newNode.id || null,
		task_runner_name: newNode.id ? null : newNode.name,
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
	refreshWorkflowRun,
	createWorkflowRun,
	loadWorkflowDefinitions,
	loadWorkflowRuns,
	setActiveWorkflowDefinition,
	addWorkflowNode
};
