<template>
	<div class="p-6">
		<div class="flex flex-nowrap items-stretch justify-between mb-4">
			<div class="flex-grow mr-4">
				<SelectField
					v-model="workflowId"
					:loading="!workflows"
					class="w-full"
					:options="workflows?.map(w => ({label: w.name, value: w.id})) || []"
					placeholder="Select a workflow"
				/>
			</div>
			<QBtn
				class="bg-lime-800 text-slate-300 px-6"
				:loading="runWorkflowAction.isApplying"
				:disable="!workflowId"
				@click="onRunWorkflow"
			>
				<RunIcon class="w-4 mr-3" />
				Run Workflow
			</QBtn>
		</div>

		<WorkflowRunCard
			v-for="workflowRun in workflowRuns"
			:key="workflowRun.id"
			:workflow-run="workflowRun"
			class="mb-4"
			@remove="loadWorkflowRuns"
		>
			<template #name>
				<a class="ml-4" @click="$router.push({name: 'workflows', params: {id: workflowRun.workflow_id}})">
					{{ workflowRun.workflow_name }}
				</a>
			</template>
		</WorkflowRunCard>
	</div>
</template>
<script setup lang="ts">
import { dxWorkflow } from "@/components/Modules/Workflows";
import { dxWorkflowInput } from "@/components/Modules/Workflows/WorkflowInputs";
import { dxWorkflowRun, WorkflowRunCard } from "@/components/Modules/Workflows/WorkflowRuns";
import { WorkflowInput } from "@/types/workflow-inputs";
import { FaSolidCirclePlay as RunIcon } from "danx-icon";
import { autoRefreshObject, SelectField, stopAutoRefreshObject, storeObjects } from "quasar-ui-danx";
import { onMounted, onUnmounted, ref, shallowRef } from "vue";

const emit = defineEmits(["run"]);
const props = defineProps<{
	workflowInput: WorkflowInput;
}>();

const workflowId = ref(null);
const workflows = shallowRef([]);
const workflowRuns = shallowRef([]);
const runWorkflowAction = dxWorkflow.getAction("run-workflow");

onMounted(() => {
	loadWorkflows();
	loadWorkflowRuns();

	autoRefreshObject(
		props.workflowInput,
		(wi: WorkflowInput) => wi.has_active_workflow_run,
		(wi: WorkflowInput) => dxWorkflowInput.routes.details(wi)
	);
});

onUnmounted(() => {
	stopAutoRefreshObject(props.workflowInput);
});

async function onRunWorkflow() {
	await runWorkflowAction.trigger({
		id: workflowId.value,
		__type: "Workflow"
	}, { workflow_input_id: props.workflowInput.id });
	await dxWorkflowInput.getActiveItemDetails();
	await loadWorkflowRuns();
	emit("run", workflowId.value);
}

async function loadWorkflows() {
	const pagedWorkflows = await dxWorkflow.routes.list({ page: 1, rowsPerPage: 1000 });
	workflows.value = storeObjects(pagedWorkflows.data);
}

async function loadWorkflowRuns() {
	const pagedWorkflowRuns = await dxWorkflowRun.routes.list({ filter: { workflow_input_id: props.workflowInput.id } });
	workflowRuns.value = storeObjects(pagedWorkflowRuns.data);
}
</script>
