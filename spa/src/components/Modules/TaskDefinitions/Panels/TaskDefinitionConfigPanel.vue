<template>
	<QTabPanel class="p-6">
		<ActionForm
			:action="updateAction"
			:target="taskDefinition"
			:form="formDefinition"
		>
			<div class="bg-sky-950 p-4 rounded">
				<ArtifactSplitModeWidget
					class="mt-4"
					:model-value="taskDefinition.artifact_split_mode"
					@update:model-value="artifact_split_mode => updateAction.trigger(taskDefinition, {artifact_split_mode})"
				/>
				<TaskArtifactFiltersWidget
					v-if="sourceTaskDefinitions"
					class="mt-8"
					:target-task-definition="taskDefinition"
					:source-task-definitions="sourceTaskDefinitions"
				/>
			</div>
			<SelectField
				class="mt-8"
				:model-value="taskDefinition.task_runner_class"
				:options="dxTaskDefinition.getFieldOptions('runners')"
				:disabled="updateAction.isApplying"
				:loading="!dxTaskDefinition.getFieldOptions('runners')"
				@update="task_runner_class => updateAction.trigger(taskDefinition, {task_runner_class})"
			/>

			<Component :is="TaskRunnerConfigComponent" :task-definition="taskDefinition" />
		</ActionForm>
	</QTabPanel>
</template>
<script setup lang="ts">
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions";
import { fields } from "@/components/Modules/TaskDefinitions/config/fields";
import { TaskRunners } from "@/components/Modules/TaskDefinitions/TaskRunners";
import ArtifactSplitModeWidget from "@/components/Modules/TaskDefinitions/Widgets/ArtifactSplitModeWidget";
import TaskArtifactFiltersWidget from "@/components/Modules/TaskDefinitions/Widgets/TaskArtifactFiltersWidget";
import { dxWorkflowNode } from "@/components/Modules/WorkflowDefinitions/WorkflowNodes/config";
import { TaskDefinition, WorkflowNode } from "@/types";
import { ActionForm, SelectField } from "quasar-ui-danx";
import { computed, onMounted, shallowRef } from "vue";

const props = defineProps<{
	taskDefinition: TaskDefinition;
	workflowNode?: WorkflowNode;
}>();

const formDefinition = shallowRef({ fields });

const updateAction = dxTaskDefinition.getAction("update");
const TaskRunnerConfigComponent = computed(() => TaskRunners[props.taskDefinition.task_runner_class]?.config || TaskRunners.Base.config);
const sourceTaskDefinitions = computed(() => props.workflowNode?.connectionsAsTarget?.map(c => c.sourceNode.taskDefinition));

onMounted(async () => {
	if (props.workflowNode) {
		await dxWorkflowNode.routes.details(props.workflowNode, { connectionsAsTarget: { sourceNode: { taskDefinition: true } } });
	}
});
</script>
