<template>
	<div class="bg-slate-600 rounded">
		<div class="p-2">
			<div class="flex items-center space-x-2">
				<div :class="colorClass" class="px-2 py-1 rounded-full text-xs">Process: {{ taskProcess.id }}</div>
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
							<div class="text-sky-300 bg-sky-950 px-4 py-.5 rounded-full text-xs">{{
									fPercent(taskProcess.percent_complete / 100)
								}}
							</div>
						</div>
					</QLinearProgress>
				</div>

				<AiTokenUsageButton v-if="taskProcess.usage" class="mx-2" :usage="taskProcess.usage" />
				<ActionButton
					v-if="taskProcess.status === 'Running'"
					type="stop"
					:action="dxTaskProcess.getAction('stop')"
					:target="taskProcess"
					color="red"
					class="mr-2"
				/>
			</div>
		</div>


	</div>
</template>
<script setup lang="ts">
import { dxTaskRun } from "@/components/Modules/TaskDefinitions/TaskRuns/config";
import { dxTaskProcess } from "@/components/Modules/TaskDefinitions/TaskRuns/TaskProcesses/config";
import { WorkflowStatusTimerPill } from "@/components/Modules/Workflows/Shared";
import ActionButton from "@/components/Shared/Buttons/ActionButton";
import AiTokenUsageButton from "@/components/Shared/Buttons/AiTokenUsageButton";
import { TaskProcess } from "@/types/task-definitions";
import { fPercent, ShowHideButton } from "quasar-ui-danx";
import { ref } from "vue";

withDefaults(defineProps<{
	taskProcess: TaskProcess;
	colorClass?: string;
}>(), {
	colorClass: "bg-sky-950 text-sky-400"
});

const isShowingInputArtifacts = ref(false);
const isShowingOutputArtifacts = ref(false);
const isShowingJobDispatches = ref(false);
</script>
