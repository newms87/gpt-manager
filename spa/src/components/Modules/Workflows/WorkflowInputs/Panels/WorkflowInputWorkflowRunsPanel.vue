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
				class="text-lg bg-lime-800 text-slate-300 px-6"
				:loading="runWorkflowAction.isApplying"
				:disable="runWorkflowAction.isApplying || !workflowId"
				@click="onRunWorkflow"
			>
				<RunIcon class="w-4 mr-3" />
				Run Workflow
			</QBtn>
		</div>

		<WorkflowRunCard
			v-for="workflowRun in workflowInput.workflowRuns"
			:key="workflowRun.id"
			:workflow-run="workflowRun"
			class="mb-4"
			@remove="WorkflowInputController.getActiveItemDetails"
		/>
	</div>
</template>
<script setup lang="ts">
import { getAction } from "@/components/Modules/Workflows/workflowActions";
import { WorkflowInputController } from "@/components/Modules/Workflows/WorkflowInputs/workflowInputControls";
import { WorkflowRunCard } from "@/components/Modules/Workflows/WorkflowRuns";
import { WorkflowRoutes } from "@/routes/workflowRoutes";
import { WorkflowInput } from "@/types/workflow-inputs";
import { FaSolidCirclePlay as RunIcon } from "danx-icon";
import { SelectField } from "quasar-ui-danx";
import { onMounted, ref, shallowRef, watch } from "vue";

const props = defineProps<{
	workflowInput: WorkflowInput;
}>();

const workflowId = ref(null);
const workflows = shallowRef([]);
const runWorkflowAction = getAction("run-workflow");

onMounted(async () => {
	workflows.value = (await WorkflowRoutes.list({ page: 1, rowsPerPage: 1000 })).data;
	refreshWorkflowInput();
});

watch(() => props.workflowInput.__timestamp, refreshWorkflowInput);

// TODO: Convert this to an action behavior / feature... Controller.startPollingActiveItemDetails()
let refreshTimeout = null;
function refreshWorkflowInput() {
	if (refreshTimeout) {
		clearTimeout(refreshTimeout);
		refreshTimeout = null;
	}

	refreshTimeout = setTimeout(() => {
		if (props.workflowInput.has_active_workflow_run) {
			WorkflowInputController.getActiveItemDetails();
		}
	}, 2000);
}

async function onRunWorkflow() {
	await runWorkflowAction.trigger({
		id: workflowId.value,
		__type: "Workflow"
	}, { workflow_input_id: props.workflowInput.id });
	await WorkflowInputController.getActiveItemDetails();
}
</script>
