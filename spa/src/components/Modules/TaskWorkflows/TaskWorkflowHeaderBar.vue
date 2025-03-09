<template>
	<div class="relative h-18">
		<div class="flex items-center flex-nowrap space-x-4">
			<div v-if="activeTaskWorkflowRun" class="flex-grow flex items-center space-x-4 flex-nowrap">
				<LabelPillWidget
					:label="`TaskWorkflowRun: ${activeTaskWorkflowRun.id}`"
					color="sky"
					size="xs"
				/>
				<WorkflowStatusTimerPill :runner="activeTaskWorkflowRun" />
			</div>
			<div>
				<ActionButton
					type="play"
					color="green"
					label="Run Workflow"
					size="sm"
					@click="isSelectingWorkflowInput = true"
				/>
			</div>
			<div>
				<ShowHideButton
					v-model="isShowing"
					:label="`${activeTaskWorkflow.runs?.length}`"
					class="bg-green-700 text-green-300"
					:show-icon="RunsIcon"
				/>
			</div>
		</div>
		<div
			class="absolute-top-right top-[120%] z-10 transition-all overflow-y-auto max-h-[80vh] w-[80vw] bg-sky-900 px-4"
			:class="{'h-[5000%]': isShowing, 'h-0': !isShowing}"
		>
			<div v-if="isShowing" class="mt-4">
				<div v-if="activeTaskWorkflow.runs?.length === 0" class="text-center text-sky-300">No Workflow Runs</div>
				<div v-else>
					<TaskWorkflowRunCard
						v-for="run in activeTaskWorkflow.runs"
						:key="run.id"
						:task-workflow="activeTaskWorkflow"
						:task-workflow-run="run"
						class="my-2"
						selectable
						@select="(activeTaskWorkflowRun = run) && (isShowing = false)"
					/>
				</div>
			</div>
			<SelectWorkflowInputDialog
				v-if="isSelectingWorkflowInput"
				@confirm="onCreateTaskWorkflowRun"
				@close="isSelectingWorkflowInput = false"
			/>
		</div>
	</div>
</template>
<script setup lang="ts">
import { WorkflowStatusTimerPill } from "@/components/Modules/TaskWorkflows/Shared";
import {
	activeTaskWorkflow,
	activeTaskWorkflowRun,
	loadTaskWorkflowRuns
} from "@/components/Modules/TaskWorkflows/store";
import TaskWorkflowRunCard from "@/components/Modules/TaskWorkflows/TaskWorkflowRunCard";
import { dxTaskWorkflowRun } from "@/components/Modules/TaskWorkflows/TaskWorkflowRuns/config";
import SelectWorkflowInputDialog from "@/components/Modules/TaskWorkflows/WorkflowInputs/SelectWorkflowInputDialog";
import { WorkflowInput } from "@/types";
import { FaSolidPersonRunning as RunsIcon } from "danx-icon";
import { ActionButton, FlashMessages, LabelPillWidget, ShowHideButton } from "quasar-ui-danx";
import { ref } from "vue";

defineEmits(["confirm", "close"]);

const isShowing = ref(false);
const isSelectingWorkflowInput = ref(false);
const createTaskWorkflowRunAction = dxTaskWorkflowRun.getAction("quick-create", { onFinish: loadTaskWorkflowRuns });

async function onCreateTaskWorkflowRun(workflowInput: WorkflowInput) {
	if (!activeTaskWorkflow.value) return;

	isSelectingWorkflowInput.value = false;

	FlashMessages.info("Creating Task Workflow Run...");
	const result = await createTaskWorkflowRunAction.trigger(null, {
		task_workflow_id: activeTaskWorkflow.value.id,
		workflow_input_id: workflowInput.id
	});
	FlashMessages.success("Task Workflow Run Created");
	activeTaskWorkflowRun.value = result.item;
}
</script>
