<template>
	<div>
		<WorkflowInputCard
			:workflow-input="taskInput.workflowInput"
			removable
			:removing="removeInputAction.isApplying"
			@remove="removeInputAction.trigger(taskDefinition, {id: taskInput.id})"
		>
			<template #actions>
				<ShowHideButton v-model="isShowingRuns" class="bg-green-900 mr-4" :show-icon="RunIcon" />
			</template>
		</WorkflowInputCard>

		<TaskInputTaskRunsList v-if="isShowingRuns" :task-definition="taskDefinition" :task-input="taskInput" />
	</div>
</template>
<script setup lang="ts">
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions";
import TaskInputTaskRunsList from "@/components/Modules/TaskDefinitions/Panels/TaskInputTaskRunsList";
import WorkflowInputCard from "@/components/Modules/Workflows/WorkflowInputs/WorkflowInputCard";
import { TaskDefinition, TaskInput } from "@/types/task-definitions";
import { FaSolidPersonRunning as RunIcon } from "danx-icon";
import { ShowHideButton } from "quasar-ui-danx";
import { ref } from "vue";

defineProps<{
	taskDefinition: TaskDefinition;
	taskInput: TaskInput;
}>();

const removeInputAction = dxTaskDefinition.getAction("remove-input");
const isShowingRuns = ref(false);
</script>
