<template>
	<ShowHideButton
		v-model="isShowingTaskProcesses"
		:show-icon="ProcessListIcon"
		class="show-task-processes-button"
		@update:model-value="refreshTaskRun(taskRun)"
	>
		<div class="ml-2">{{ taskRun.process_count }}</div>
		<InfoDialog
			v-if="isShowingTaskProcesses"
			:title="`${taskRun.taskDefinition.name}: Task Processes`"
			@close="isShowingTaskProcesses = false"
		>
			<ListTransition class="w-[70rem] h-[80vh] overflow-x-hidden overflow-y-auto">
				<QSkeleton v-if="taskRun.processes?.length === undefined" class="h-12" />
				<div
					v-else-if="taskRun.processes.length === 0"
					class="text-center text-gray-500 font-bold h-12 flex items-center justify-center"
				>
					There are no processes for this task
				</div>
				<NodeTaskProcessCard
					v-for="taskProcess in taskRun.processes"
					:key="taskProcess.id"
					:task-process="taskProcess"
					class="bg-slate-700 p-2 my-2 rounded-lg"
					@restart="onRestart"
				/>
			</ListTransition>
		</InfoDialog>
	</ShowHideButton>
</template>

<script setup lang="ts">
import { dxTaskRun } from "@/components/Modules/TaskDefinitions/TaskRuns/config";
import NodeTaskProcessCard from "@/components/Modules/WorkflowCanvas/NodeTaskProcessCard";
import { TaskRun } from "@/types";
import { FaSolidFileInvoice as ProcessListIcon } from "danx-icon";
import { InfoDialog, ListTransition, ShowHideButton } from "quasar-ui-danx";
import { ref } from "vue";

const emit = defineEmits<{ restart: void }>();
const props = defineProps<{
	taskRun: TaskRun;
}>();

const artifactsField = {
	text_content: true,
	json_content: true,
	meta: true,
	files: { transcodes: true, thumb: true }
};

// Handle auto refreshing task processes while they're being shown
const isShowingTaskProcesses = ref(false);

function onRestart() {
	refreshTaskRun(props.taskRun);
	emit("restart");
}
async function refreshTaskRun(taskRun: TaskRun) {
	return await dxTaskRun.routes.details(taskRun, {
		processes: {
			inputArtifacts: artifactsField,
			outputArtifacts: artifactsField
		}
	});
}
</script>
