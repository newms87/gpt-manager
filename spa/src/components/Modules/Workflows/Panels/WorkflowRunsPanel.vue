<template>
	<div class="p-6">
		<div class="flex items-start flex-nowrap">
			<QBtn class="bg-sky-800 mt-5" :class="{'px-8': !selectedInput }" @click="showInputDialog = true">
				<template v-if="selectedInput">
					<ChangeIcon class="w-6" />
				</template>
				<template v-else>Choose Workflow Input</template>
			</QBtn>
			<div class="flex-grow flex items-center flex-nowrap">
				<WorkflowInputCard
					v-if="selectedInput"
					:key="selectedInput.id"
					class="ml-4 w-full"
					:workflow-input="selectedInput"
				/>
				<SelectWorkflowInputDialog
					v-if="showInputDialog"
					@close="showInputDialog = false"
					@confirm="onConfirmSelection"
				/>
			</div>
			<ActionButton
				:action="dxWorkflow.getAction('run-workflow')"
				:target="workflow"
				:input="actionInput"
				:disabled="!selectedInput"
				type="play"
				:color="selectedInput ? 'green' : 'gray'"
				icon-class="w-4"
				class="px-6 py-3 mt-4"
			/>
		</div>

		<QSeparator class="bg-slate-400 my-6" />
		<WorkflowRunCard
			v-for="workflowRun in workflow.runs"
			:key="workflowRun.id"
			:workflow-run="workflowRun"
			class="mb-2"
		>
			<template #name>
				<QBtn
					v-if="workflowRun.input_name"
					class="px-4 py-1 bg-sky-800 text-sky-200 ml-4 rounded-full"
					@click="loadInputFromRun(workflowRun)"
				>
					{{ workflowRun.input_name }}
				</QBtn>
			</template>
		</WorkflowRunCard>
	</div>
</template>
<script setup lang="ts">
import { dxWorkflow } from "@/components/Modules/Workflows";
import { dxWorkflowInput } from "@/components/Modules/Workflows/WorkflowInputs";
import SelectWorkflowInputDialog from "@/components/Modules/Workflows/WorkflowInputs/SelectWorkflowInputDialog";
import WorkflowInputCard from "@/components/Modules/Workflows/WorkflowInputs/WorkflowInputCard";
import { WorkflowRunCard } from "@/components/Modules/Workflows/WorkflowRuns";
import { ActionButton } from "@/components/Shared";
import { Workflow, WorkflowInput, WorkflowRun } from "@/types";
import { FaSolidMagnifyingGlass as ChangeIcon } from "danx-icon";
import { storeObject } from "quasar-ui-danx";
import { computed, ref } from "vue";

defineProps<{
	workflow: Workflow,
}>();

const selectedInput = ref<WorkflowInput | null>(null);
const actionInput = computed(() => ({ workflow_input_id: selectedInput.value?.id }));
const showInputDialog = ref<boolean>(false);

function loadInputFromRun(workflowRun: WorkflowRun) {
	const input = storeObject({
		id: workflowRun.input_id,
		name: workflowRun.input_name,
		__type: "WorkflowInputResource"
	});
	onConfirmSelection(input);
}

async function onConfirmSelection(input: WorkflowInput) {
	selectedInput.value = input;
	showInputDialog.value = false;
	storeObject(await dxWorkflowInput.routes.details(input));
}
</script>
