<template>
	<div class="p-6">
		<ArtifactSplitModeWidget
			:model-value="taskDefinition.artifact_split_mode"
			@update:model-value="artifact_split_mode => updateAction.trigger(taskDefinition, {artifact_split_mode})"
		/>
		<SelectField
			class="mt-8"
			:model-value="taskDefinition.task_runner_class"
			:options="dxTaskDefinition.getFieldOptions('runners')"
			:disable="updateAction.isApplying"
			:loading="!dxTaskDefinition.getFieldOptions('runners')"
			@update="task_runner_class => updateAction.trigger(taskDefinition, {task_runner_class})"
		/>
		<TaskDefinitionAgentList v-if="showAgentList" class="mt-8" :task-definition="taskDefinition" />
	</div>
</template>
<script setup lang="ts">
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions";
import TaskDefinitionAgentList from "@/components/Modules/TaskDefinitions/Panels/TaskDefinitionAgentList";
import ArtifactSplitModeWidget from "@/components/Modules/TaskDefinitions/Widgets/ArtifactSplitModeWidget";
import { TaskDefinition } from "@/types";
import { SelectField } from "quasar-ui-danx";
import { ref } from "vue";

defineProps<{
	taskDefinition: TaskDefinition,
}>();

const updateAction = dxTaskDefinition.getAction("update");
const showAgentList = ref(true);
</script>
