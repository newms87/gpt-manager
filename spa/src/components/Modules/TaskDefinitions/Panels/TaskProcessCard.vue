<template>
	<div class="bg-slate-600 rounded">
		<div class="p-2">
			<div class="flex items-center space-x-2">
				<LabelPillWidget :label="`TaskProcess: ${taskProcess.id}`" color="sky" size="xs" />
				<div class="flex-grow w-96">
					{{ taskProcess.name || "(No Name)" }}
				</div>
				<ShowHideButton
					v-model="isShowingJobDispatches"
					:label="taskProcess.job_dispatch_count + ' Jobs'"
					:class="colorClass"
					@show="dxTaskRun.routes.detailsAndStore(taskProcess, {jobDispatches: true})"
				/>
				<ShowHideButton
					v-model="isShowingInputArtifacts"
					:label="taskProcess.input_artifact_count + ' Input'"
					:class="colorClass"
					@show="dxTaskRun.routes.detailsAndStore(taskProcess, {inputArtifacts: true})"
				/>
				<ShowHideButton
					v-model="isShowingOutputArtifacts"
					:label="taskProcess.output_artifact_count + ' Output'"
					:class="colorClass"
					@show="dxTaskRun.routes.detailsAndStore(taskProcess, {inputArtifacts: true})"
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
				/>
			</div>
		</div>

		<div v-if="isShowingJobDispatches">
			<AuditRequestJobsPanel v-if="taskProcess.jobDispatches" :jobs="taskProcess.jobDispatches" />
			<div v-else>
				<QSkeleton v-for="i in 3" :key="'s' + i" class="h-12 my-2" />
			</div>
		</div>
	</div>
</template>
<script setup lang="ts">
import { AuditRequestJobsPanel } from "@/components/Modules/Audits/Panels";
import { dxTaskRun } from "@/components/Modules/TaskDefinitions/TaskRuns/config";
import { dxTaskProcess } from "@/components/Modules/TaskDefinitions/TaskRuns/TaskProcesses/config";
import { WORKFLOW_STATUS } from "@/components/Modules/Workflows/config/workflows";
import { WorkflowStatusTimerPill } from "@/components/Modules/Workflows/Shared";
import ActionButton from "@/components/Shared/Buttons/ActionButton";
import AiTokenUsageButton from "@/components/Shared/Buttons/AiTokenUsageButton";
import LabelPillWidget from "@/components/Shared/Widgets/LabelPillWidget";
import { TaskProcess } from "@/types/task-definitions";
import { autoRefreshObject, fPercent, ShowHideButton, stopAutoRefreshObject } from "quasar-ui-danx";
import { onMounted, onUnmounted, ref } from "vue";

const props = withDefaults(defineProps<{
	taskProcess: TaskProcess;
	colorClass?: string;
}>(), {
	colorClass: "bg-sky-950 text-sky-400"
});

const stopAction = dxTaskProcess.getAction("stop");
const isShowingInputArtifacts = ref(false);
const isShowingOutputArtifacts = ref(false);
const isShowingJobDispatches = ref(false);

/********
 * Refresh the task run every 2 seconds while it is running
 */
onMounted(() => {
	autoRefreshObject(
		props.taskProcess,
		(tp: TaskProcess) => [WORKFLOW_STATUS.PENDING.value, WORKFLOW_STATUS.RUNNING.value].includes(tp.status),
		(tp: TaskProcess) => dxTaskProcess.routes.details(tp, {
			jobDispatches: isShowingJobDispatches.value ? { logs: true, apiLogs: true, errors: true } : false,
			inputArtifacts: isShowingInputArtifacts.value,
			outputArtifacts: isShowingOutputArtifacts.value
		})
	);
});

onUnmounted(() => {
	stopAutoRefreshObject(props.taskProcess);
});
</script>
