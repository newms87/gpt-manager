<template>
	<div class="node-header" draggable="false" @click.prevent.stop @mousedown.prevent.stop>
		<div class="flex flex-nowrap items-center space-x-2 h-8">
			<div class="flex-grow flex-x space-x-1">
				<ShowTaskProcessesButton
					v-if="taskRun"
					:task-run="taskRun"
					class="bg-sky-950"
					size="xs"
					@restart="$emit('restart')"
				/>
				<ActionButton
					v-if="isRunning"
					type="stop"
					:disabled="!isRunning"
					:action="stopAction"
					:target="taskRun"
					color="red"
					tooltip="Stop task"
					size="xs"
				/>
				<ActionButton
					v-else-if="isStopped"
					type="play"
					:action="resumeAction"
					:target="taskRun"
					color="green-invert"
					tooltip="Continue running task"
					size="xs"
				/>
				<ActionButton
					v-if="canBeRestarted"
					type="refresh"
					:action="restartAction"
					:target="taskRun"
					color="sky"
					tooltip="Restart task"
					size="xs"
				/>
			</div>
			<template v-if="!isWorkflowRunning">
				<ActionButton
					type="copy"
					color="blue"
					:disabled="temporary"
					size="xs"
					tooltip="Copy Task"
					:saving="isCopying"
					@click.stop="onCopy"
				/>
				<ActionButton
					type="edit"
					color="sky"
					:disabled="temporary"
					size="xs"
					tooltip="Edit Task"
					@click.stop="$emit('edit')"
				/>
				<ActionButton
					type="minus"
					color="red"
					:disabled="temporary"
					size="xs"
					tooltip="Remove Task From Workflow"
					@click.stop="$emit('remove')"
				/>
			</template>
		</div>
	</div>
</template>

<script setup lang="ts">
import { dxTaskRun } from "@/components/Modules/TaskDefinitions/TaskRuns/config";
import ShowTaskProcessesButton from "@/components/Modules/WorkflowCanvas/ShowTaskProcessesButton";
import { activeWorkflowRun, refreshActiveWorkflowRun } from "@/components/Modules/WorkflowDefinitions/store";
import { TaskRun } from "@/types";
import { ActionButton } from "quasar-ui-danx";
import { computed, ref } from "vue";

const emit = defineEmits<{
	edit: void;
	remove: void;
	copy: void;
	restart: void;
}>();
const props = defineProps<{
	taskRun?: TaskRun;
	temporary?: boolean;
	loading?: boolean;
}>();

const isWorkflowRunning = computed(() => ["Running"].includes(activeWorkflowRun.value?.status));

const restartAction = dxTaskRun.getAction("restart", { onFinish: refreshActiveWorkflowRun });
const resumeAction = dxTaskRun.getAction("resume", { onFinish: refreshActiveWorkflowRun });
const stopAction = dxTaskRun.getAction("stop");
const isStopped = computed(() => ["Stopped"].includes(props.taskRun?.status));
const canBeRestarted = computed(() => ["Pending", "Stopped", "Failed", "Completed"].includes(props.taskRun?.status));
const isRunning = computed(() => ["Running"].includes(props.taskRun?.status));

const isCopying = ref(false);
async function onCopy() {
	emit("copy");
	isCopying.value = true;
	setTimeout(() => isCopying.value = false, 3000);
}
</script>
