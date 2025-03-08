<template>
	<ShowHideButton
		v-model="isShowingTaskProcesses"
		:show-icon="ProcessListIcon"
	>
		<template #label>
			<div class="ml-2">{{ taskRun.process_count }}</div>
			<QMenu
				self="center right"
				max-height="80vh"
				cover
				:model-value="isShowingTaskProcesses"
				@before-hide="isShowingTaskProcesses = false"
				@hide="isShowingTaskProcesses = false"
			>
				<ListTransition class="p-4 w-[70rem] h-[80vh] overflow-x-hidden overflow-y-auto">
					<QSkeleton v-if="taskRun.processes?.length === undefined" class="h-12" />
					<div
						v-else-if="taskRun.processes.length === 0"
						class="text-center text-gray-500 font-bold h-12 flex items-center justify-center"
					>
						There are no processes for this task
					</div>
					<NodeTaskProcessWidget
						v-for="taskProcess in taskRun.processes"
						:key="taskProcess.id"
						:task-process="taskProcess"
						class="bg-slate-700 p-4 my-2 rounded-lg"
					/>
				</ListTransition>
			</QMenu>
		</template>
	</ShowHideButton>
</template>

<script setup lang="ts">
import { dxTaskRun } from "@/components/Modules/TaskDefinitions/TaskRuns/config";
import NodeTaskProcessWidget from "@/components/Modules/WorkflowCanvas/NodeTaskProcessWidget";
import { TaskRun } from "@/types";
import { FaSolidFileInvoice as ProcessListIcon } from "danx-icon";
import { autoRefreshObject, ListTransition, ShowHideButton, stopAutoRefreshObject } from "quasar-ui-danx";
import { onMounted, ref, watch } from "vue";

const props = defineProps<{
	taskRun: TaskRun;
}>();

const artifactsField = {
	text_content: true,
	json_content: true,
	files: { transcodes: true, thumb: true }
};

// Handle auto refreshing task processes while they're being shown
const isShowingTaskProcesses = ref(false);
let autoRefreshId = "";
watch(() => props.taskRun, registerAutoRefresh);
onMounted(registerAutoRefresh);

function registerAutoRefresh() {
	if (props.taskRun) {
		autoRefreshId = "task-run-task-processes:" + props.taskRun.id;
		autoRefreshObject(
			autoRefreshId,
			props.taskRun,
			(tr: TaskRun) => isShowingTaskProcesses.value && (!tr.processes?.length || ["Running", "Pending"].includes(tr.status)),
			(tr: TaskRun) => dxTaskRun.routes.details(tr, {
				processes: {
					inputArtifacts: artifactsField,
					outputArtifacts: artifactsField
				}
			})
		);
	} else if (autoRefreshId) {
		stopAutoRefreshObject(autoRefreshId);
	}
}
</script>
