<template>
	<div
		class="absolute-bottom w-full px-6 py-4 bg-sky-900 transition-all overflow-y-auto"
		:class="{'h-[80vh]': isShowing, 'h-16': !isShowing}"
	>
		<div class="flex items-center flex-nowrap space-x-4">
			<div class="flex-grow">{{ taskWorkflow.runs?.length || 0 }} Workflow Runs</div>
			<div>
				<ActionButton
					type="play"
					color="green"
					label="Run Workflow"
					size="sm"
					:action="createTaskWorkflowRunAction"
					:input="{'task_workflow_id': taskWorkflow.id}"
				/>
			</div>
			<div>
				<ShowHideButton v-model="isShowing" class="bg-sky-700 text-sky-300" />
			</div>
		</div>
		<div v-if="isShowing" class="mt-4">
			<div v-if="taskWorkflow.runs?.length === 0" class="text-center text-sky-300">No Workflow Runs</div>
			<div v-else>
				<TaskWorkflowRunCard
					v-for="run in taskWorkflow.runs"
					:key="run.id"
					:task-workflow="taskWorkflow"
					:task-workflow-run="run"
					class="my-2"
				/>
			</div>
		</div>
	</div>
</template>
<script setup lang="ts">
import { dxTaskWorkflow } from "@/components/Modules/TaskWorkflows/config";
import TaskWorkflowRunCard from "@/components/Modules/TaskWorkflows/TaskWorkflowRunCard";
import { dxTaskWorkflowRun } from "@/components/Modules/TaskWorkflows/TaskWorkflowRuns/config";
import { ActionButton } from "@/components/Shared";
import { TaskWorkflow } from "@/types/task-workflows";
import { ShowHideButton } from "quasar-ui-danx";
import { onMounted, ref } from "vue";

defineEmits(["confirm", "close"]);
const props = defineProps<{
	taskWorkflow: TaskWorkflow;
}>();
onMounted(loadTaskWorkflowRuns);

const createTaskWorkflowRunAction = dxTaskWorkflowRun.getAction("quick-create");

const isShowing = ref(false);

async function loadTaskWorkflowRuns() {
	await dxTaskWorkflow.routes.detailsAndStore(props.taskWorkflow, { runs: { taskRuns: true } });
}
</script>
