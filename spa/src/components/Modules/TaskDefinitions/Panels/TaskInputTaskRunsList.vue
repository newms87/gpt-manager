<template>
	<div>
		<div class="flex justify-end">
			<QBtn
				class="bg-green-900 text-green-300 px-4 py-1"
				:loading="createTaskRunAction.isApplying"
				@click="createTaskRunAction.trigger(null, {task_definition_id: taskDefinition.id, task_input_id: taskInput.id})"
			>
				<RunTaskIcon class="w-3 mr-2" />
				Run Task
			</QBtn>
		</div>
		<div class="my-4">
			<div v-if="isLoading">
				<QSkeleton class="h-12" />
			</div>
			<div
				v-else-if="taskInput.taskRuns?.length === 0"
				class="text-center text-gray-500 font-bold h-12 flex items-center justify-center"
			>
				No task runs have been executed for this task input.
			</div>
			<div v-else>
				<ListTransition>
					<template v-for="taskRun in taskInput.taskRuns" :key="taskRun.id">
						<TaskRunsCard :task-run="taskRun" />
						<QSeparator class="bg-slate-400 my-2" />
					</template>
				</ListTransition>
			</div>
		</div>
	</div>
</template>
<script setup lang="ts">
import TaskRunsCard from "@/components/Modules/TaskDefinitions/Panels/TaskRunsCard";
import { routes } from "@/components/Modules/TaskDefinitions/TaskInputs/config/routes";
import { dxTaskRun } from "@/components/Modules/TaskDefinitions/TaskRuns/config";
import { TaskDefinition, TaskInput } from "@/types/task-definitions";
import { FaSolidPlay as RunTaskIcon } from "danx-icon";
import { ListTransition } from "quasar-ui-danx";
import { onMounted, ref } from "vue";

const props = defineProps<{
	taskDefinition: TaskDefinition;
	taskInput: TaskInput;
}>();

const isLoading = ref(false);
const createTaskRunAction = dxTaskRun.getAction("quick-create", { onFinish: loadTaskInput });
onMounted(loadTaskInput);

async function loadTaskInput() {
	isLoading.value = true;
	await routes.detailsAndStore(props.taskInput);
	isLoading.value = false;
}
</script>
