<template>
	<div class="py-2">
		<div class="flex items-center">
			<div class="flex flex-grow mx-2">
				<div class="bg-sky-950 text-sky-400 px-2 py-1 rounded-full text-xs">{{ taskProcess.id }}</div>
				<div v-if="taskProcess.label"></div>
			</div>
			<ShowHideButton
				v-model="isShowingJobDispatches"
				:label="taskProcess.job_dispatch_count + ' Jobs'"
				class="bg-slate-600 text-slate-200 mx-2"
				@show="dxTaskRun.routes.detailsAndStore(taskProcess, {jobDispatches: true})"
			/>
			<ShowHideButton
				v-model="isShowingInputArtifacts"
				:label="taskProcess.input_artifact_count + ' Input Artifacts'"
				class="bg-slate-600 text-slate-200 mx-2"
				@show="dxTaskRun.routes.detailsAndStore(taskProcess, {inputArtifacts: true})"
			/>
			<ShowHideButton
				v-model="isShowingOutputArtifacts"
				:label="taskProcess.output_artifact_count + ' Output Artifacts'"
				class="bg-slate-600 text-slate-200 mx-2"
				@show="dxTaskRun.routes.detailsAndStore(taskProcess, {inputArtifacts: true})"
			/>
			<WorkflowStatusTimerPill :runner="taskProcess" />
			<AiTokenUsageButton v-if="taskProcess.usage" class="mx-2" :usage="taskProcess.usage" />
			<div class="mr-1">
				<ActionButton
					type="stop"
					:action="dxTaskProcess.getAction('stop')"
					:target="taskProcess"
					class="p-4"
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
import { ShowHideButton } from "quasar-ui-danx";
import { ref } from "vue";

defineProps<{
	taskProcess: TaskProcess;
}>();

const isShowingInputArtifacts = ref(false);
const isShowingOutputArtifacts = ref(false);
const isShowingJobDispatches = ref(false);
</script>
