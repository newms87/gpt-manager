<template>
	<QCard class="bg-indigo-900 text-indigo-200 rounded overflow-hidden p-4">
		<div class="flex items-center">
			<div class="flex-grow flex-nowrap flex items-center">
				<div class="text-lg">{{ jobRun.workflowJob.name }} ({{ jobRun.id }})</div>
				<div class="text-base text-sky-500 ml-3">{{ jobRun.tasks.length }} Tasks</div>
			</div>
			<div class="text-lg font-bold">{{ jobRun.status }}</div>
		</div>
		<div class="mt-4">
			<div class="flex items-stretch flex-nowrap task-selector flex-shrink-0">
				<div
					v-for="tab in tabs"
					:key="tab.status.value"
					class="task-menu-item"
					:class="{'is-active' : tasksTab === tab.status.value, [tab.status.classPrimary]: true}"
					@click="tasksTab = tab.status.value"
				>{{
						tab.tasks.length
					}}
					{{ tab.label }}
					Tasks
				</div>
			</div>
			<div class="p-3 w-full min-h-56" :class="workflowStatus.classPrimary">
				<template v-for="(task, index) in displayTasks" :key="task.id">
					<WorkflowTaskCard :task="task" />
					<QSeparator v-if="index !== displayTasks.length - 1" :class="workflowStatus.classAlt" class="my-3" />
				</template>
			</div>
		</div>
	</QCard>
</template>
<script setup lang="ts">
import { WORKFLOW_STATUS } from "@/components/Modules/Workflows/consts/workflows";
import WorkflowTaskCard from "@/components/Modules/Workflows/WorkflowRuns/WorkflowTaskCard";
import { WorkflowJobRun } from "@/types/workflows";
import { computed, shallowRef } from "vue";

const props = defineProps<{
	jobRun: WorkflowJobRun;
	defaultTab?: string;
}>();

const tasksTab = shallowRef(props.defaultTab || WORKFLOW_STATUS.PENDING.value);
const pendingTasks = computed(() => props.jobRun.tasks.filter(t => t.status === WORKFLOW_STATUS.PENDING.value) || []);
const runningTasks = computed(() => props.jobRun.tasks.filter(t => t.status === WORKFLOW_STATUS.RUNNING.value) || []);
const completedTasks = computed(() => props.jobRun.tasks.filter(t => t.status === WORKFLOW_STATUS.COMPLETED.value) || []);
const failedTasks = computed(() => props.jobRun.tasks.filter(t => t.status === WORKFLOW_STATUS.FAILED.value) || []);
const displayTasks = computed(() => props.jobRun.tasks.filter(t => t.status === tasksTab.value) || []);

const workflowStatus = computed(() => WORKFLOW_STATUS.resolve(tasksTab.value));

const tabs = computed(() => [
	{
		status: WORKFLOW_STATUS.PENDING,
		label: "Pending",
		tasks: pendingTasks.value
	},
	{
		status: WORKFLOW_STATUS.RUNNING,
		label: "Running",
		tasks: runningTasks.value
	},
	{
		status: WORKFLOW_STATUS.COMPLETED,
		label: "Completed",
		tasks: completedTasks.value
	},
	{
		status: WORKFLOW_STATUS.FAILED,
		label: "Failed",
		tasks: failedTasks.value
	}
]);
</script>

<style lang="scss" scoped>
.task-menu-item {
	@apply rounded-t p-2 cursor-pointer opacity-70;

	&:hover, &.is-active {
		@apply opacity-100;
	}
}
</style>
