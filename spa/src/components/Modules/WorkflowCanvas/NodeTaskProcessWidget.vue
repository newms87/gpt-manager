<template>
	<div class="flex items-start flex-nowrap space-x-4">
		<NodeArtifactsButton
			class="mt-6"
			:count="taskProcess.inputArtifacts.length"
			active-color="sky"
			:disabled="!taskProcess"
			@show="isShowingInputArtifacts = true"
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
						@update:model-value="loadJobDispatches"
					/>
				</div>
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
			<ArtifactList
				v-if="artifactsToShow"
				dense
				class="bg-slate-800 p-2 mt-4 rounded max-w-full"
				:artifacts="artifactsToShow"
			/>
			<JobDispatchList
				v-if="isShowingJobDispatches"
				class="p-2 border-t border-slate-400 mt-2"
				:jobs="taskProcess.jobDispatches"
			/>
		</div>
		<NodeArtifactsButton
			class="mt-6"
			:count="taskProcess.outputArtifacts.length"
			active-color="green"
			:disabled="!taskProcess"
			@show="isShowingOutputArtifacts = true"
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
import { ActionButton, fPercent, LabelPillWidget, ShowHideButton } from "quasar-ui-danx";
import { computed, ref } from "vue";

const props = defineProps<{
	taskProcess: TaskProcess;
}>();

const resumeAction = dxTaskProcess.getAction("resume");
const stopAction = dxTaskProcess.getAction("stop");
const isStopped = computed(() => props.taskProcess.status === "Stopped" || props.taskProcess.status === "Pending");
const isRunning = computed(() => ["Running"].includes(props.taskProcess.status));
const isShowingJobDispatches = ref(false);
const isShowingInputArtifacts = ref(false);
const isShowingOutputArtifacts = ref(false);
const artifactsToShow = computed(() => {
	if (isShowingInputArtifacts.value) {
		return props.taskProcess.inputArtifacts;
	} else if (isShowingOutputArtifacts.value) {
		return props.taskProcess.outputArtifacts;
	}
	return null;
});

async function loadJobDispatches() {
	await dxTaskProcess.routes.details(props.taskProcess, { jobDispatches: { logs: true, apiLogs: true, errors: true } });
}
</script>
