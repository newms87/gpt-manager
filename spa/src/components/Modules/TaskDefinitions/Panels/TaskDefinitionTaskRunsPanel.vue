<template>
	<div class="p-6">
		<QBtn
			class="bg-green-900 text-green-300 px-8"
			:loading="addInputAction.isApplying"
			@click="isSelectingInput = true"
		>
			<AddIcon class="w-3 mr-2" />
			Add Task Input
		</QBtn>
		<SelectWorkflowInputDialog
			v-if="isSelectingInput"
			@confirm="workflowInput => (addInputAction.trigger(taskDefinition, { workflow_input_id: workflowInput.id }) && (isSelectingInput = false))"
			@close="isSelectingInput = false"
		/>

		<ListTransition class="mt-8">
			<template
				v-for="taskInput in taskDefinition.taskInputs"
				:key="taskInput.id"
			>
				<TaskInputCard
					:task-definition="taskDefinition"
					:task-input="taskInput"
				/>
				<QSeparator class="bg-slate-400 my-2" />
			</template>
		</ListTransition>
	</div>
</template>
<script setup lang="ts">
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions";
import TaskInputCard from "@/components/Modules/TaskDefinitions/Panels/TaskInputCard";
import SelectWorkflowInputDialog from "@/components/Modules/Workflows/WorkflowInputs/SelectWorkflowInputDialog";
import { TaskDefinition } from "@/types/task-definitions";
import { FaSolidPlus as AddIcon } from "danx-icon";
import { ListTransition } from "quasar-ui-danx";
import { ref } from "vue";

defineProps<{
	taskDefinition: TaskDefinition;
}>();

const addInputAction = dxTaskDefinition.getAction("add-input");
const isSelectingInput = ref(false);
</script>
