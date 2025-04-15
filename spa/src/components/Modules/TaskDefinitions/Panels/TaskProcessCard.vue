<template>
	<div class="bg-slate-600 rounded">
		<div class="p-2">
			<div class="flex items-center space-x-2">
				<LabelPillWidget :label="`pid: ${taskProcess.id}`" color="sky" size="xs" class="whitespace-nowrap" />
				<div class="flex-grow w-96">
					{{ taskProcess.name || "(No Name)" }}
				</div>
				<ShowHideButton
					v-model="isShowingAgentThread"
					label="Thread"
					:show-icon="AgentThreadIcon"
					:class="colorClass"
					@show="dxTaskProcess.routes.details(taskProcess, {agentThread: agentThreadField})"
				/>
				<ShowHideButton
					v-model="isShowingJobDispatches"
					:label="taskProcess.job_dispatch_count + ' Jobs'"
					:class="colorClass"
					@show="dxTaskProcess.routes.details(taskProcess, {jobDispatches: jobDispatchesField})"
				/>
				<ShowHideButton
					v-model="isShowingInputArtifacts"
					:label="taskProcess.input_artifact_count + ' Input'"
					:class="colorClass"
					@show="dxTaskProcess.routes.details(taskProcess, {inputArtifacts: artifactsField})"
				/>
				<ShowHideButton
					v-model="isShowingOutputArtifacts"
					:label="taskProcess.output_artifact_count + ' Output'"
					:class="colorClass"
					@show="dxTaskProcess.routes.details(taskProcess, {outputArtifacts: artifactsField})"
				/>
				<WorkflowStatusTimerPill :runner="taskProcess" />
			</div>
			<div class="flex items-center space-x-2 flex-nowrap mt-1">
				<div v-if="taskProcess.activity" class="flex-grow rounded px-2 py-1 bg-slate-900 text-slate-500">
					{{ taskProcess.activity }}
				</div>
				<div class="w-96 overflow-hidden">
					<QLinearProgress size="29px" :value="taskProcess.percent_complete / 100" class="w-full rounded bg-sky-950">
						<div class="absolute-full flex flex-center">
							<LabelPillWidget :label="fPercent(taskProcess.percent_complete / 100)" color="sky" size="xs" />
						</div>
					</QLinearProgress>
				</div>

				<AiTokenUsageButton v-if="taskProcess.usage" class="mx-2" :usage="taskProcess.usage" />
				<ActionButton
					v-if="taskProcess.status === 'Running'"
					type="stop"
					:action="stopAction"
					:target="taskProcess"
					color="red"
					class="mr-2"
					tooltip="Stop Task Process"
				/>
				<ActionButton
					v-else
					:action="resumeProcessAction"
					:target="taskProcess"
					:icon="ResumeProcessIcon"
					color="sky"
					size="sm"
					tooltip="Resume / Restart Task Process"
				/>
			</div>
		</div>

		<div v-if="isShowingAgentThread" class="p-2 border-t border-slate-400 mt-2">
			<div class="flex-x mb-2 space-x-2">
				<ShowHideButton v-model="isEditingAgentThread" class="bg-slate-800 text-slate-300" />
				<ActionButton
					:action="resumeProcessAction"
					:target="taskProcess"
					label="Run Thread"
					:icon="RunThreadIcon"
					color="green"
					size="sm"
				/>
			</div>
			<template v-if="taskProcess.agentThread">
				<ThreadMessageCard
					v-for="message in taskProcess.agentThread.messages"
					:key="message.id"
					:message="message"
					:thread="taskProcess.agentThread"
					class="mb-5"
					:readonly="!isEditingAgentThread"
				/>
			</template>
			<QSkeleton v-else class="h-30" />
		</div>
		<JobDispatchList
			v-if="isShowingJobDispatches"
			class="p-2 border-t border-slate-400 mt-2"
			:jobs="taskProcess.jobDispatches"
		/>
		<ArtifactList
			v-if="isShowingInputArtifacts"
			class="p-2 border-t border-slate-400 mt-2"
			:artifacts="taskProcess.inputArtifacts"
		/>
		<ArtifactList
			v-if="isShowingOutputArtifacts"
			class="p-2 border-t border-slate-400 mt-2"
			:artifacts="taskProcess.outputArtifacts"
		/>
	</div>
</template>
<script setup lang="ts">
import ThreadMessageCard from "@/components/Modules/Agents/Threads/ThreadMessageCard";
import ArtifactList from "@/components/Modules/Artifacts/ArtifactList";
import JobDispatchList from "@/components/Modules/Audits/JobDispatches/JobDispatchList";
import { dxTaskProcess } from "@/components/Modules/TaskDefinitions/TaskRuns/TaskProcesses/config";
import { WorkflowStatusTimerPill } from "@/components/Modules/WorkflowDefinitions/Shared";
import { WORKFLOW_STATUS } from "@/components/Modules/WorkflowDefinitions/workflows";
import AiTokenUsageButton from "@/components/Shared/Buttons/AiTokenUsageButton";
import { TaskProcess } from "@/types";
import {
	FaSolidArrowsRotate as ResumeProcessIcon,
	FaSolidMessage as AgentThreadIcon,
	FaSolidPersonRunning as RunThreadIcon
} from "danx-icon";
import {
	ActionButton,
	autoRefreshObject,
	fPercent,
	LabelPillWidget,
	ShowHideButton,
	stopAutoRefreshObject
} from "quasar-ui-danx";
import { onMounted, onUnmounted, ref } from "vue";

const props = withDefaults(defineProps<{
	taskProcess: TaskProcess;
	colorClass?: string;
}>(), {
	colorClass: "bg-sky-950 text-sky-400"
});

const stopAction = dxTaskProcess.getAction("stop");
const resumeProcessAction = dxTaskProcess.getAction("resume");

const isShowingAgentThread = ref(false);
const isEditingAgentThread = ref(false);
const isShowingInputArtifacts = ref(false);
const isShowingOutputArtifacts = ref(false);
const isShowingJobDispatches = ref(false);

// Defines the fields to fetch when requesting the AgentThread
const agentThreadField = { messages: { files: { thumb: true, transcodes: true } } };

// Defines the fields to fetch when requesting JobDispatches
const jobDispatchesField = { logs: true, apiLogs: true, errors: true };

// Defines the fields to fetch when requesting artifacts
const artifactsField = {
	text_content: true,
	json_content: true,
	meta: true,
	files: { transcodes: true, thumb: true }
};

/********
 * Refresh the task run every 2 seconds while it is running
 */
const autoRefreshId = "task-process:" + props.taskProcess.id;
onMounted(() => {
	autoRefreshObject(
		autoRefreshId,
		props.taskProcess,
		(tp: TaskProcess) => [WORKFLOW_STATUS.PENDING.value, WORKFLOW_STATUS.RUNNING.value, WORKFLOW_STATUS.DISPATCHED.value].includes(tp.status),
		(tp: TaskProcess) => dxTaskProcess.routes.details(tp, {
			agentThread: isShowingAgentThread.value ? agentThreadField : false,
			jobDispatches: isShowingJobDispatches.value ? jobDispatchesField : false,
			inputArtifacts: isShowingInputArtifacts.value ? artifactsField : false,
			outputArtifacts: isShowingOutputArtifacts.value ? artifactsField : false
		})
	);
});

onUnmounted(() => {
	stopAutoRefreshObject(autoRefreshId);
});
</script>
