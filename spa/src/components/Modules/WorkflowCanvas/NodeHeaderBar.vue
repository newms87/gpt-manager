<template>
	<div class="node-header flex flex-nowrap items-center space-x-2">
		<div class="flex-grow">
			<ShowTaskProcessesButton v-if="taskRun" :task-run="taskRun" class="bg-sky-950 py-0" icon-class="w-3" />
		</div>
		<template v-if="isWorkflowRunning">
			<ActionButton
				v-if="isRunning"
				type="stop"
				:disabled="!isRunning"
				:action="stopAction"
				:target="taskRun"
				color="red"
				tooltip="Stop task"
				class="p-2"
			/>
			<ActionButton
				v-else-if="isStopped"
				type="play"
				:action="resumeAction"
				:target="taskRun"
				color="green-invert"
				tooltip="Continue running task"
				class="p-2"
			/>
			<ActionButton
				v-else-if="canBeRestarted"
				type="refresh"
				:action="restartAction"
				:target="taskRun"
				color="sky"
				tooltip="Restart task"
				class="p-2"
			/>
		</template>
		<template v-else>
			<template v-if="loading">
				<QSpinner size="lg" />
			</template>
			<template v-else>
				<ActionButton type="edit" color="sky" :disabled="temporary" @click.stop="$emit('edit')" />
				<ActionButton type="trash" color="red" :disabled="temporary" @click.stop="$emit('remove')" />
			</template>
		</template>
	</div>
</template>

<script setup lang="ts">
import { dxTaskRun } from "@/components/Modules/TaskDefinitions/TaskRuns/config";
import { activeTaskWorkflowRun } from "@/components/Modules/TaskWorkflows/store";
import ShowTaskProcessesButton from "@/components/Modules/WorkflowCanvas/ShowTaskProcessesButton";
import { TaskRun } from "@/types";
import { ActionButton } from "quasar-ui-danx";
import { computed } from "vue";

defineEmits<{
	edit: void;
	remove: void;
}>();
const props = defineProps<{
	taskRun?: TaskRun;
	temporary?: boolean;
	loading?: boolean;
}>();

const isWorkflowRunning = computed(() => ["Running"].includes(activeTaskWorkflowRun.value?.status));

const restartAction = dxTaskRun.getAction("restart");
const resumeAction = dxTaskRun.getAction("resume");
const stopAction = dxTaskRun.getAction("stop");
const isStopped = computed(() => ["Stopped"].includes(props.taskRun?.status));
const canBeRestarted = computed(() => ["Pending", "Failed", "Completed"].includes(props.taskRun?.status));
const isRunning = computed(() => ["Running"].includes(props.taskRun?.status));
</script>
