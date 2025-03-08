<template>
	<div class="flex items-center flex-nowrap space-x-2">
		<NodeArtifactsButton
			:count="taskRun?.input_artifacts_count"
			active-color="sky"
			:disabled="!taskRun"
			:artifacts="taskRun?.inputArtifacts"
			@show="onShowInputArtifacts"
		/>
		<div class="flex-grow flex items-center flex-nowrap space-x-2">
			<slot />
		</div>
		<NodeArtifactsButton
			:count="taskRun?.output_artifacts_count"
			active-color="green"
			:disabled="!taskRun"
			:artifacts="taskRun?.outputArtifacts"
			@show="onShowOutputArtifacts"
		/>
	</div>
</template>

<script setup lang="ts">
import { dxTaskRun } from "@/components/Modules/TaskDefinitions/TaskRuns/config";
import NodeArtifactsButton from "@/components/Modules/WorkflowCanvas/NodeArtifactsButton";
import { TaskRun } from "@/types";

const props = defineProps<{
	taskRun?: TaskRun;
}>();

const artifactsField = {
	text_content: true,
	json_content: true,
	files: { transcodes: true, thumb: true }
};
async function onShowInputArtifacts() {
	await dxTaskRun.routes.details(props.taskRun, { inputArtifacts: artifactsField });
}
async function onShowOutputArtifacts() {
	await dxTaskRun.routes.details(props.taskRun, { outputArtifacts: artifactsField });
}
</script>
