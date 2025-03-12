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
				<div>
					<ShowHideButton
						v-model="isShowingJobDispatches"
						:label="taskProcess.job_dispatch_count"
						class="bg-slate-800 text-slate-400"
						:show-icon="JobDispatchIcon"
						size="sm"
						@update:model-value="loadJobDispatches"
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
			<ListTransition>
				<ArtifactList
					v-if="isShowingInputArtifacts"
					title="Input Artifacts"
					title-class="text-sky-300"
					dense
					class="bg-sky-950 p-2 rounded max-w-full mt-4"
					:artifacts="taskProcess.inputArtifacts"
				/>
				<ArtifactList
					v-if="isShowingOutputArtifacts"
					title="Output Artifacts"
					title-class="text-green-300"
					dense
					class="bg-green-950 p-2 rounded max-w-full mt-4"
					:artifacts="taskProcess.outputArtifacts"
				/>
				<JobDispatchList
					v-if="isShowingJobDispatches"
					class="p-2 border-t border-slate-400 mt-2"
					:jobs="taskProcess.jobDispatches"
				/>
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
import ArtifactList from "@/components/Modules/Artifacts/ArtifactList";
import JobDispatchList from "@/components/Modules/Audits/JobDispatches/JobDispatchList";
import { dxTaskProcess } from "@/components/Modules/TaskDefinitions/TaskRuns/TaskProcesses/config";
import { WorkflowStatusTimerPill } from "@/components/Modules/TaskWorkflows/Shared";
import NodeArtifactsButton from "@/components/Modules/WorkflowCanvas/NodeArtifactsButton";
import { TaskProcess } from "@/types";
import { FaSolidBusinessTime as JobDispatchIcon } from "danx-icon";
import { ActionButton, fPercent, LabelPillWidget, ListTransition, ShowHideButton } from "quasar-ui-danx";
import { computed, ref } from "vue";

const emit = defineEmits<{ restart: void }>();
const props = defineProps<{
	taskProcess: TaskProcess;
}>();

const restartAction = dxTaskProcess.getAction("restart", { onFinish: async () => emit("restart") });
const resumeAction = dxTaskProcess.getAction("resume");
const stopAction = dxTaskProcess.getAction("stop");
const isStopped = computed(() => props.taskProcess.status === "Stopped" || props.taskProcess.status === "Pending");
const isRunning = computed(() => ["Running"].includes(props.taskProcess.status));
const isShowingJobDispatches = ref(false);
const isShowingInputArtifacts = ref(false);
const isShowingOutputArtifacts = ref(false);

async function loadJobDispatches() {
	await dxTaskProcess.routes.details(props.taskProcess, { jobDispatches: { logs: true, apiLogs: true, errors: true } });
}
</script>
