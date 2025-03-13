<template>
	<div class="flex items-start flex-nowrap space-x-2">
		<NodeArtifactsButton
			class="mt-2"
			:count="taskProcess.inputArtifacts.length"
			active-color="sky"
			:disabled="!taskProcess"
			@show="isShowingInputArtifacts = !isShowingInputArtifacts"
		/>
		<div class="flex-grow min-w-0 overflow-hidden">
			<div v-if="taskProcess.activity" class="flex-grow flex items-center flex-nowrap space-x-2">
				<div class="flex-grow rounded p-2 bg-slate-900 text-slate-400">
					{{ taskProcess.activity }}
				</div>
				<div class="space-x-2">
					<ShowHideButton
						v-model="isShowingAgentThread"
						:show-icon="AgentThreadIcon"
						tooltip="Manage Agent Thread"
						color="sky"
						size="sm"
						@show="refreshAgentThreadRelation"
					/>
					<ShowHideButton
						v-model="isShowingJobDispatches"
						:label="taskProcess.job_dispatch_count"
						class="bg-slate-800 text-slate-400"
						:show-icon="JobDispatchIcon"
						size="sm"
						@update:model-value="refreshJobDispatchesRelation"
					/>
				</div>
			</div>
			<div class="flex items-center flex-nowrap space-x-2 mt-2">
				<LabelPillWidget :label="taskProcess.id" color="sky" size="xs" />
				<LabelPillWidget :label="taskProcess.name" color="blue" size="xs" />
				<div class="flex-grow overflow-hidden">
					<QLinearProgress size="29px" :value="taskProcess.percent_complete / 100" class="w-full rounded bg-sky-950">
						<div class="absolute-full flex flex-center">
							<LabelPillWidget :label="fPercent(taskProcess.percent_complete / 100)" color="sky" size="xs" />
						</div>
					</QLinearProgress>
				</div>
				<WorkflowStatusTimerPill :runner="taskProcess" class="text-xs" />
				<ActionButton
					v-if="!isRunning"
					type="play"
					:disabled="!isStopped"
					:action="resumeAction"
					:target="taskProcess"
					color="green-invert"
					tooltip="Continue running process"
					size="sm"
				/>
				<ActionButton
					v-else
					type="stop"
					:action="stopAction"
					:target="taskProcess"
					color="red"
					tooltip="Stop process"
					size="sm"
				/>
				<ActionButton
					type="refresh"
					:disabled="isRunning"
					:action="restartAction"
					:target="taskProcess"
					color="sky"
					tooltip="Restart process. NOTE: This will delete any existing output artifacts created by this process."
					size="sm"
				/>
			</div>
			<ListTransition class="space-y-4 mt-4">
				<ArtifactList
					v-if="isShowingInputArtifacts"
					title="Input Artifacts"
					title-class="text-sky-300"
					dense
					class="bg-sky-950 p-2 rounded max-w-full"
					:artifacts="taskProcess.inputArtifacts"
				/>
				<ArtifactList
					v-if="isShowingOutputArtifacts"
					title="Output Artifacts"
					title-class="text-green-300"
					dense
					class="bg-green-950 p-2 rounded max-w-full"
					:artifacts="taskProcess.outputArtifacts"
				/>
				<JobDispatchList
					v-if="isShowingJobDispatches"
					class="p-2 border-t border-slate-400"
					:jobs="taskProcess.jobDispatches"
				/>
				<div v-if="isShowingAgentThread" class="bg-slate-900 p-4 rounded">
					<TaskProcessAgentThreadCard v-if="taskProcess.agentThread" :agent-thread="taskProcess.agentThread" />
					<QSkeleton v-else class="h-24" />
				</div>
			</ListTransition>
		</div>
		<NodeArtifactsButton
			class="mt-2"
			:count="taskProcess.outputArtifacts.length"
			active-color="green"
			:disabled="!taskProcess"
			@show="isShowingOutputArtifacts = !isShowingOutputArtifacts"
		/>
	</div>
</template>

<script setup lang="ts">
import { AgentThreadFields } from "@/components/Modules/Agents/Threads/store";
import ArtifactList from "@/components/Modules/Artifacts/ArtifactList";
import JobDispatchList from "@/components/Modules/Audits/JobDispatches/JobDispatchList";
import { dxTaskProcess } from "@/components/Modules/TaskDefinitions/TaskRuns/TaskProcesses/config";
import { WorkflowStatusTimerPill } from "@/components/Modules/TaskWorkflows/Shared";
import NodeArtifactsButton from "@/components/Modules/WorkflowCanvas/NodeArtifactsButton";
import TaskProcessAgentThreadCard from "@/components/Modules/WorkflowCanvas/TaskProcessAgentThreadCard";
import { TaskProcess } from "@/types";
import { FaSolidBusinessTime as JobDispatchIcon, FaSolidMessage as AgentThreadIcon } from "danx-icon";
import { ActionButton, fPercent, LabelPillWidget, ListTransition, ShowHideButton } from "quasar-ui-danx";
import { computed, ref, watch } from "vue";

const emit = defineEmits<{ restart: void }>();
const props = defineProps<{
	taskProcess: TaskProcess;
}>();

const restartAction = dxTaskProcess.getAction("restart", { onFinish: async () => emit("restart") });
const resumeAction = dxTaskProcess.getAction("resume");
const stopAction = dxTaskProcess.getAction("stop");
const isStopped = computed(() => props.taskProcess.status === "Stopped" || props.taskProcess.status === "Pending");
const isRunning = computed(() => ["Running"].includes(props.taskProcess.status));
const isShowingAgentThread = ref(false);
const isShowingJobDispatches = ref(false);
const isShowingInputArtifacts = ref(false);
const isShowingOutputArtifacts = ref(false);

async function refreshJobDispatchesRelation() {
	await dxTaskProcess.routes.details(props.taskProcess, { jobDispatches: { logs: true, apiLogs: true, errors: true } });
}

watch(() => props.taskProcess.status, () => {
	if (isRunning.value && isShowingAgentThread.value && !props.taskProcess.agentThread?.is_running) {
		refreshAgentThreadRelation();
	}
});

async function refreshAgentThreadRelation() {
	const result = await dxTaskProcess.routes.details(props.taskProcess, { agentThread: AgentThreadFields });

	// If the process is running, but the thread is not, the thread is probably out of sync / or will start running soon, so keep checking until it is running or the process has stopped running.
	if (isRunning.value && !result.agentThread.is_running) {
		setTimeout(refreshAgentThreadRelation, 1000);
	}
}
</script>
