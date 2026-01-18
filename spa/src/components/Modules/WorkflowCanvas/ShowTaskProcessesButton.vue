<template>
	<ShowHideButton
		v-model="isShowingTaskProcesses"
		:show-icon="ProcessListIcon"
		class="show-task-processes-button"
		:label="taskRun.process_count"
	>
		<InfoDialog
			v-if="isShowingTaskProcesses"
			:title="`${taskRun.taskDefinition?.name || 'Task'} (${taskRun.id}): Task Processes`"
			hide-done
			@close="isShowingTaskProcesses = false"
		>
			<div class="w-[85vw] h-[80vh] overflow-hidden">
				<TaskProcessList
					:task-run-id="taskRun.id"
					:show-batch-actions="true"
					:enable-web-socket="true"
					:per-page="10"
					@process-restarted="onProcessRestarted"
				/>
			</div>
		</InfoDialog>
	</ShowHideButton>
</template>

<script setup lang="ts">
import TaskProcessList from "@/components/Modules/WorkflowCanvas/TaskProcessList";
import { activeWorkflowRun, refreshWorkflowRun } from "@/components/Modules/WorkflowDefinitions/store";
import { TaskProcess, TaskRun } from "@/types";
import { FaSolidFileInvoice as ProcessListIcon } from "danx-icon";
import { InfoDialog, ShowHideButton } from "quasar-ui-danx";
import { ref } from "vue";

defineProps<{
	taskRun: TaskRun;
}>();

const isShowingTaskProcesses = ref(false);

function onProcessRestarted(_oldProcessId?: number, _newProcess?: TaskProcess) {
	// Refresh the workflow run to pick up any state changes from the restarted process
	if (activeWorkflowRun.value) {
		refreshWorkflowRun(activeWorkflowRun.value);
	}
}
</script>
