<template>
	<BaseTaskRunnerConfig :task-definition="taskDefinition">
		<div class="flex-x text-lg font-bold mt-8">
			<RunWorkflowLottie class="w-[6rem] mr-4" />
			<SelectionMenuField
				:selected="selectedWorkflowDefinition"
				selectable
				:select-icon="WorkflowIcon"
				label-class="text-slate-300"
				:options="workflowDefinitions"
				:loading="isLoadingWorkflowDefinitions"
				@update:selected="onSelect"
			/>
		</div>
	</BaseTaskRunnerConfig>
</template>
<script setup lang="ts">
import { RunWorkflowLottie } from "@/assets/dotlottie";
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions";
import { isLoadingWorkflowDefinitions, workflowDefinitions } from "@/components/Modules/WorkflowDefinitions/store";
import { TaskDefinition, WorkflowDefinition } from "@/types";
import { FaSolidAnkh as WorkflowIcon } from "danx-icon";
import { SelectionMenuField } from "quasar-ui-danx";
import { computed } from "vue";
import BaseTaskRunnerConfig from "./BaseTaskRunnerConfig";

const props = defineProps<{
	taskDefinition: TaskDefinition;
}>();

const selectedWorkflowDefinition = computed(() => workflowDefinitions.value.find(w => w.id === props.taskDefinition.task_runner_config?.workflow_definition_id));

const updateTaskDefinitionAction = dxTaskDefinition.getAction("update");

async function onSelect(workflowDefinition: WorkflowDefinition) {
	await updateTaskDefinitionAction.trigger(props.taskDefinition, {
		task_runner_config: {
			workflow_definition_id: workflowDefinition.id
		}
	});
}
</script>
