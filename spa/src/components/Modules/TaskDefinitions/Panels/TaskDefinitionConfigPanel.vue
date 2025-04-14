<template>
	<QTabPanel class="p-6">
		<ActionForm
			:action="updateAction"
			:target="taskDefinition"
			:form="formDefinition"
		>
			<div v-if="!taskDefinition.is_trigger" class="mt-4 bg-sky-950 p-4 rounded">
				<ArtifactSplitModeWidget
					:model-value="taskDefinition.artifact_split_mode"
					:levels="taskDefinition.input_artifact_levels"
					@update:model-value="artifact_split_mode => updateAction.trigger(taskDefinition, {artifact_split_mode})"
					@update:levels="input_artifact_levels => updateAction.trigger(taskDefinition, {input_artifact_levels})"
				/>
			</div>

			<Component
				:is="TaskRunnerConfigComponent"
				:task-definition="taskDefinition"
				:source-task-definitions="sourceTaskDefinitions"
			/>
		</ActionForm>
	</QTabPanel>
</template>
<script setup lang="ts">
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions";
import { fields } from "@/components/Modules/TaskDefinitions/config/fields";
import { TaskRunnerClasses } from "@/components/Modules/TaskDefinitions/TaskRunners";
import ArtifactSplitModeWidget from "@/components/Modules/TaskDefinitions/Widgets/ArtifactSplitModeWidget";
import { dxWorkflowNode } from "@/components/Modules/WorkflowDefinitions/WorkflowNodes/config";
import { TaskDefinition, WorkflowNode } from "@/types";
import { ActionForm } from "quasar-ui-danx";
import { computed, onMounted, shallowRef } from "vue";

const props = defineProps<{
	taskDefinition: TaskDefinition;
	workflowNode?: WorkflowNode;
}>();

const formDefinition = shallowRef({ fields });

const updateAction = dxTaskDefinition.getAction("update");
const TaskRunnerConfigComponent = computed(() => TaskRunnerClasses[props.taskDefinition.task_runner_name]?.config || TaskRunnerClasses["AI Agent"].config);
const sourceTaskDefinitions = computed(() => props.workflowNode?.connectionsAsTarget?.map(c => c.sourceNode.taskDefinition));

onMounted(async () => {
	if (props.workflowNode) {
		await dxWorkflowNode.routes.details(props.workflowNode, { connectionsAsTarget: { sourceNode: { taskDefinition: true } } });
	}
});
</script>
