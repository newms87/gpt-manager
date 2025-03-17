<template>
	<div class="p-6">
		<ActionForm
			:action="updateAction"
			:target="taskDefinition"
			:form="formDefinition"
		>
			<ArtifactSplitModeWidget
				class="mt-4"
				:model-value="taskDefinition.artifact_split_mode"
				@update:model-value="artifact_split_mode => updateAction.trigger(taskDefinition, {artifact_split_mode})"
			/>
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
	</div>
</template>
<script setup lang="ts">
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions";
import { fields } from "@/components/Modules/TaskDefinitions/config/fields";
import { TaskRunners } from "@/components/Modules/TaskDefinitions/TaskRunners";
import ArtifactSplitModeWidget from "@/components/Modules/TaskDefinitions/Widgets/ArtifactSplitModeWidget";
import { TaskDefinition } from "@/types";
import { ActionForm, SelectField } from "quasar-ui-danx";
import { computed, shallowRef } from "vue";

const props = defineProps<{
	taskDefinition: TaskDefinition,
}>();

const formDefinition = shallowRef({ fields });

const updateAction = dxTaskDefinition.getAction("update");
const TaskRunnerConfigComponent = computed(() => TaskRunners[props.taskDefinition.task_runner_class]?.config || TaskRunners.Base.config);
</script>
