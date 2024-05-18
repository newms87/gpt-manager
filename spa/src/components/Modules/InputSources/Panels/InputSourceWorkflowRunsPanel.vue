<template>
	<div class="p-6">
		<div class="flex flex-nowrap items-stretch justify-between">
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
				class="text-lg mb-5 bg-lime-800 text-slate-300"
				:loading="runWorkflowAction.isApplying"
				:disable="runWorkflowAction.isApplying || !workflowId"
				@click="onRunWorkflow"
			>
				<RunIcon class="w-4 mr-3" />
				Run Workflow
			</QBtn>
		</div>

		<WorkflowRunCard
			v-for="workflowRun in inputSource.workflowRuns"
			:key="workflowRun.id"
			:workflow-run="workflowRun"
			class="mb-4"
			@remove="InputSourceController.getActiveItemDetails"
		/>
	</div>
</template>
<script setup lang="ts">
import { InputSourceController } from "@/components/Modules/InputSources/inputSourceControls";
import { getAction } from "@/components/Modules/Workflows/workflowActions";
import { WorkflowRunCard } from "@/components/Modules/Workflows/WorkflowRuns";
import { WorkflowRoutes } from "@/routes/workflowRoutes";
import { InputSource } from "@/types/input-sources";
import { FaSolidCirclePlay as RunIcon } from "danx-icon";
import { SelectField } from "quasar-ui-danx";
import { onMounted, ref, shallowRef, watch } from "vue";

const props = defineProps<{
	inputSource: InputSource;
}>();

const workflowId = ref(null);
const workflows = shallowRef([]);
const runWorkflowAction = getAction("run-workflow");

onMounted(async () => {
	workflows.value = (await WorkflowRoutes.list({ page: 1, rowsPerPage: 1000 })).data;
	refreshInputSource();
});

watch(() => props.inputSource.__timestamp, refreshInputSource);

let refreshTimeout = null;
function refreshInputSource() {
	if (refreshTimeout) {
		clearTimeout(refreshTimeout);
		refreshTimeout = null;
	}

	if (props.inputSource.has_active_workflow_run) {
		refreshTimeout = setTimeout(() => {
			InputSourceController.getActiveItemDetails();
		}, 1000);
	}
}

async function onRunWorkflow() {
	await runWorkflowAction.trigger({
		id: workflowId.value,
		__type: "Workflow"
	}, { input_source_id: props.inputSource.id });
	await InputSourceController.getActiveItemDetails();
}
</script>
