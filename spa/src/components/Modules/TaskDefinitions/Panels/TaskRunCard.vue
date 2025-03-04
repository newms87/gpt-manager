<template>
	<div class="bg-sky-900 rounded">
		<div class="flex items-center p-2 space-x-2">
			<div class="flex flex-grow mx-2 space-x-2">
				<LabelPillWidget :label="`TaskRun: ${taskRun.id}`" color="sky" size="xs" />
				<LabelPillWidget :label="taskRun.step" color="green" size="xs" />
				<div>{{ taskRun.name }}</div>
			</div>
			<ShowHideButton
				v-model="isShowingProcesses"
				:label="taskRun.process_count + ' Processes'"
				class="bg-slate-600 text-slate-200"
				@show="dxTaskRun.routes.details(taskRun, {processes: true})"
			/>
			<WorkflowStatusTimerPill :runner="taskRun" />
			<AiTokenUsageButton v-if="taskRun.usage" :usage="taskRun.usage" />
			<ActionButton
				v-if="isStopped"
				type="play"
				:action="resumeAction"
				:target="taskRun"
				color="green-invert"
				tooltip="Continue running task"
				class="p-2"
			/>
			<ActionButton
				v-else
				type="stop"
				:disabled="!isRunning"
				:action="stopAction"
				:target="taskRun"
				color="red"
				tooltip="Stop task"
				class="p-2"
			/>
			<ActionButton
				type="trash"
				:action="deleteAction"
				:target="taskRun"
				tooltip="Delete task run"
				class="p-2 ml-2"
				@success="$emit('deleted')"
			/>
		</div>

		<ListTransition v-if="isShowingProcesses" class="px-2 pb-2">
			<TaskProcessCard
				v-for="taskProcess in taskRun.processes"
				:key="taskProcess.id"
				:task-process="taskProcess"
				class="my-2"
			/>
			<div v-if="taskRun.processes?.length === undefined">
				<QSkeleton class="h-12" />
			</div>
			<div
				v-else-if="taskRun.processes.length === 0"
				class="text-center text-gray-500 font-bold h-12 flex items-center justify-center"
			>
				No processes have been executed for this task run.
			</div>
		</ListTransition>
	</div>
</template>
<script setup lang="ts">
import TaskProcessCard from "@/components/Modules/TaskDefinitions/Panels/TaskProcessCard";
import { dxTaskRun } from "@/components/Modules/TaskDefinitions/TaskRuns/config";
import { WORKFLOW_STATUS } from "@/components/Modules/Workflows/config/workflows";
import { WorkflowStatusTimerPill } from "@/components/Modules/Workflows/Shared";
import ActionButton from "@/components/Shared/Buttons/ActionButton";
import AiTokenUsageButton from "@/components/Shared/Buttons/AiTokenUsageButton";
import LabelPillWidget from "@/components/Shared/Widgets/LabelPillWidget";
import { TaskRun } from "@/types/task-definitions";
import { autoRefreshObject, ListTransition, ShowHideButton, stopAutoRefreshObject } from "quasar-ui-danx";
import { computed, onMounted, onUnmounted, ref } from "vue";

defineEmits(["deleted"]);
const props = defineProps<{
	taskRun: TaskRun;
}>();

const resumeAction = dxTaskRun.getAction("resume");
const stopAction = dxTaskRun.getAction("stop");
const deleteAction = dxTaskRun.getAction("delete");

const isShowingProcesses = ref(false);
const isStopped = computed(() => props.taskRun.status === "Stopped" || props.taskRun.status === "Pending");
const isRunning = computed(() => props.taskRun.status === "Running");

/********
 * Refresh the task run every 2 seconds while it is running
 */
onMounted(() => {
	autoRefreshObject(
		props.taskRun,
		(tr: TaskRun) => [WORKFLOW_STATUS.PENDING.value, WORKFLOW_STATUS.RUNNING.value].includes(tr.status),
		(tr: TaskRun) => dxTaskRun.routes.details(tr, { processes: false })
	);
});

onUnmounted(() => {
	stopAutoRefreshObject(props.taskRun);
});
</script>
