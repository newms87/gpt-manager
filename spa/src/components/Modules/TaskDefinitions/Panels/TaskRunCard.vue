<template>
	<div class="bg-sky-900 rounded">
		<div class="flex items-center">
			<div class="flex flex-grow mx-2 space-x-2">
				<div class="bg-sky-950 text-sky-400 px-2 py-1 rounded-full text-xs">Task Run: {{ taskRun.id }}</div>
				<div class="bg-green-950 text-green-400 px-2 py-1 rounded-full text-xs">{{ taskRun.step }}</div>
				<div>{{ taskRun.name }}</div>
			</div>
			<ShowHideButton
				v-model="isShowingProcesses"
				:label="taskRun.process_count + ' Processes'"
				class="bg-slate-600 text-slate-200 mx-2"
				@show="dxTaskRun.routes.detailsAndStore(taskRun, {processes: true})"
			/>
			<WorkflowStatusTimerPill :runner="taskRun" />
			<AiTokenUsageButton v-if="taskRun.usage" class="mx-2" :usage="taskRun.usage" />
			<div class="mr-1">
				<ActionButton
					type="trash"
					:action="dxTaskRun.getAction('delete')"
					:target="taskRun"
					class="p-4"
					@success="$emit('deleted')"
				/>
			</div>
		</div>

		<ListTransition v-if="isShowingProcesses" class="px-2 pb-2">
			<TaskProcessCard
				v-for="taskProcess in taskRun.processes"
				:key="taskProcess.id"
				:task-process="taskProcess"
				class="my-2"
			/>
			<div v-if="taskRun.processes?.length === undefined">
				<QSkeleton class="h-12" />
			</div>
			<div
				v-else-if="taskRun.processes.length === 0"
				class="text-center text-gray-500 font-bold h-12 flex items-center justify-center"
			>
				No processes have been executed for this task run.
			</div>
		</ListTransition>
	</div>
</template>
<script setup lang="ts">
import TaskProcessCard from "@/components/Modules/TaskDefinitions/Panels/TaskProcessCard";
import { dxTaskRun } from "@/components/Modules/TaskDefinitions/TaskRuns/config";
import { WorkflowStatusTimerPill } from "@/components/Modules/Workflows/Shared";
import ActionButton from "@/components/Shared/Buttons/ActionButton";
import AiTokenUsageButton from "@/components/Shared/Buttons/AiTokenUsageButton";
import { TaskRun } from "@/types/task-definitions";
import { ListTransition, ShowHideButton } from "quasar-ui-danx";
import { ref } from "vue";

defineEmits(["deleted"]);
defineProps<{
	taskRun: TaskRun;
}>();

const isShowingProcesses = ref(false);
</script>
