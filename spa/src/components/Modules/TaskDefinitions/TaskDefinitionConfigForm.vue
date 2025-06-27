<template>
	<div>
		<div class="mb-8">
			<EditableDiv
				:model-value="taskDefinition.description"
				color="slate-600"
				placeholder="Enter Description..."
				@update:model-value="description => updateAction.trigger(taskDefinition, {description})"
			/>
		</div>
		<div v-if="!taskDefinition.is_trigger" class="mt-4 bg-sky-950 p-4 rounded border-2 border-sky-600 text-sky-200">
			<div class="text-sky-400 font-bold text-lg mb-4">Artifact Input Setup</div>
			<ArtifactSplitModeWidget
				:model-value="taskDefinition.input_artifact_mode"
				@update:model-value="input_artifact_mode => updateAction.trigger(taskDefinition, {input_artifact_mode})"
			/>
			<ArtifactLevelsField
				mode="input"
				:levels="taskDefinition.input_artifact_levels"
				@update:levels="input_artifact_levels => updateAction.trigger(taskDefinition, {input_artifact_levels})"
			/>
		</div>

		<div class="mt-4 bg-green-950 p-4 rounded border-2 border-green-600 text-green-200">
			<div class="text-green-400 font-bold text-lg mb-4">Artifact Output Setup</div>
			<ArtifactOutputModeWidget
				:model-value="taskDefinition.output_artifact_mode"
				@update:model-value="output_artifact_mode => updateAction.trigger(taskDefinition, {output_artifact_mode})"
			/>
			<ArtifactLevelsField
				mode="output"
				:levels="taskDefinition.output_artifact_levels"
				@update:levels="output_artifact_levels => updateAction.trigger(taskDefinition, {output_artifact_levels})"
			/>
		</div>

		<Component
			:is="TaskRunnerConfigComponent"
			:task-definition="taskDefinition"
			:source-task-definitions="sourceTaskDefinitions"
		/>
	</div>
</template>
<script setup lang="ts">
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions";
import { TaskRunnerClasses } from "@/components/Modules/TaskDefinitions/TaskRunners";
import ArtifactLevelsField from "@/components/Modules/TaskDefinitions/TaskRunners/Configs/Fields/ArtifactLevelsField";
import ArtifactOutputModeWidget from "@/components/Modules/TaskDefinitions/Widgets/ArtifactOutputModeWidget";
import ArtifactSplitModeWidget from "@/components/Modules/TaskDefinitions/Widgets/ArtifactSplitModeWidget";
import { dxWorkflowNode } from "@/components/Modules/WorkflowDefinitions/WorkflowNodes/config";
import { TaskDefinition, WorkflowNode } from "@/types";
import { EditableDiv } from "quasar-ui-danx";
import { computed, onMounted } from "vue";

const props = defineProps<{
	taskDefinition: TaskDefinition;
	workflowNode?: WorkflowNode;
}>();

const updateAction = dxTaskDefinition.getAction("update");
const TaskRunnerConfigComponent = computed(() => TaskRunnerClasses[props.taskDefinition.task_runner_name]?.config || TaskRunnerClasses["AI Agent"].config);
const sourceTaskDefinitions = computed(() => props.workflowNode?.connectionsAsTarget?.filter(c => !!c.sourceNode).map(c => c.sourceNode.taskDefinition));

onMounted(async () => {
	if (props.workflowNode) {
		await dxWorkflowNode.routes.details(props.workflowNode, { connectionsAsTarget: { sourceNode: { taskDefinition: true } } });
	}
});
</script>
