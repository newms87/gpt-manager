<template>
	<div class="flex items-center flex-nowrap space-x-4">
		<NodeArtifactsButton
			:count="taskProcess.inputArtifacts.length"
			active-color="sky"
			:disabled="!taskProcess"
			:artifacts="taskProcess.inputArtifacts"
		/>
		<div class="flex-grow">
			<div v-if="taskProcess.activity" class="flex-grow rounded p-2 bg-slate-900 text-slate-400">
				{{ taskProcess.activity }}
			</div>
			<div class="flex items-center flex-nowrap space-x-2 mt-2">
				<div class="flex-grow overflow-hidden">
					<QLinearProgress size="29px" :value="taskProcess.percent_complete / 100" class="w-full rounded bg-sky-950">
						<div class="absolute-full flex flex-center">
							<LabelPillWidget :label="fPercent(taskProcess.percent_complete / 100)" color="sky" size="xs" />
						</div>
					</QLinearProgress>
				</div>
				<WorkflowStatusTimerPill :runner="taskProcess" class="text-xs" />
				<ActionButton
					v-if="isStopped"
					type="play"
					:action="resumeAction"
					:target="taskProcess"
					color="green-invert"
					tooltip="Continue running process"
					class="p-2"
				/>
				<ActionButton
					v-else
					type="stop"
					:disabled="!isRunning"
					:action="stopAction"
					:target="taskProcess"
					color="red"
					tooltip="Stop process"
					class="p-2"
				/>
			</div>
		</div>
		<NodeArtifactsButton
			:count="taskProcess.outputArtifacts.length"
			active-color="green"
			:disabled="!taskProcess"
			:artifacts="taskProcess.outputArtifacts"
		/>
	</div>
</template>

<script setup lang="ts">
import { dxTaskProcess } from "@/components/Modules/TaskDefinitions/TaskRuns/TaskProcesses/config";
import { WorkflowStatusTimerPill } from "@/components/Modules/TaskWorkflows/Shared";
import NodeArtifactsButton from "@/components/Modules/WorkflowCanvas/NodeArtifactsButton";
import { TaskProcess } from "@/types";
import { ActionButton, fPercent, LabelPillWidget } from "quasar-ui-danx";
import { computed } from "vue";

const props = defineProps<{
	taskProcess: TaskProcess;
}>();

const resumeAction = dxTaskProcess.getAction("resume");
const stopAction = dxTaskProcess.getAction("stop");
const isStopped = computed(() => props.taskProcess.status === "Stopped" || props.taskProcess.status === "Pending");
const isRunning = computed(() => ["Running"].includes(props.taskProcess.status));
</script>
