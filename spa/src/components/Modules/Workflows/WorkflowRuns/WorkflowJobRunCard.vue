<template>
	<QCard class="rounded overflow-hidden p-4 bg-sky-950">
		<div class="flex items-center">
			<div class="flex-grow flex-nowrap flex items-center">
				<div class="text-base">{{ jobRun.workflowJob.name }} ({{ jobRun.id }})</div>
				<div class="text-base text-sky-500 ml-3">
					<SelectField v-model="tasksTab" :options="options" />
				</div>
			</div>
			<div
				class="flex items-center flex-nowrap text-md font-bold py-2 px-4 rounded-xl"
				:class="workflowStatus.classPrimary"
			>
				<div>{{ jobRun.status }}</div>
				<RestartIcon
					v-if="jobRun.status !== WORKFLOW_STATUS.PENDING.value"
					class="w-4 cursor-pointer	ml-2"
					@click="restartJobAction.trigger(workflowRun, {workflow_job_run_id: jobRun.id})"
				/>
			</div>
			<div class="ml-2">
				<WorkflowCostsButton :usage="jobRun.usage" />
			</div>
		</div>
		<div class="mt-4 w-full">
			<WorkflowTaskCard
				v-for="task in displayTasks"
				:key="task.id"
				:task="task"
				class="my-1"
			/>
		</div>
	</QCard>
</template>
<script setup lang="ts">
import { WORKFLOW_STATUS } from "@/components/Modules/Workflows/consts/workflows";
import { getAction } from "@/components/Modules/Workflows/workflowRunActions";
import WorkflowCostsButton from "@/components/Modules/Workflows/WorkflowRuns/WorkflowCostsButton";
import WorkflowTaskCard from "@/components/Modules/Workflows/WorkflowRuns/WorkflowTaskCard";
import { WorkflowJobRun, WorkflowRun } from "@/types/workflows";
import { FaSolidArrowsRotate as RestartIcon } from "danx-icon";
import { SelectField } from "quasar-ui-danx";
import { computed, shallowRef } from "vue";

const props = defineProps<{
	workflowRun: WorkflowRun;
	jobRun: WorkflowJobRun;
	defaultTab?: string;
}>();

const restartJobAction = getAction("restart-job");
const tasksTab = shallowRef(props.defaultTab || "all");
const pendingTasks = computed(() => props.jobRun.tasks.filter(t => t.status === WORKFLOW_STATUS.PENDING.value) || []);
const runningTasks = computed(() => props.jobRun.tasks.filter(t => t.status === WORKFLOW_STATUS.RUNNING.value) || []);
const completedTasks = computed(() => props.jobRun.tasks.filter(t => t.status === WORKFLOW_STATUS.COMPLETED.value) || []);
const failedTasks = computed(() => props.jobRun.tasks.filter(t => t.status === WORKFLOW_STATUS.FAILED.value) || []);
const displayTasks = computed(() => tasksTab.value === "all" ? props.jobRun.tasks : props.jobRun.tasks.filter(t => t.status === tasksTab.value));

const workflowStatus = computed(() => WORKFLOW_STATUS.resolve(props.jobRun.status));

const options = computed(() => [
	{
		value: "all",
		label: `All ${props.jobRun.tasks.length} Tasks`
	},
	{
		value: WORKFLOW_STATUS.PENDING.value,
		label: pendingTasks.value.length + " Pending Tasks"
	},
	{
		value: WORKFLOW_STATUS.RUNNING.value,
		label: runningTasks.value.length + " Running Tasks"
	},
	{
		value: WORKFLOW_STATUS.COMPLETED.value,
		label: completedTasks.value.length + " Completed Tasks"
	},
	{
		value: WORKFLOW_STATUS.FAILED.value,
		label: failedTasks.value.length + " Failed Tasks"
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
