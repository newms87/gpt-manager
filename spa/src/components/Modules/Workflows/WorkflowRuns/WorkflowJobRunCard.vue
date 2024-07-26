<template>
	<QCard class="rounded overflow-hidden p-4 bg-sky-950">
		<div class="flex items-center">
			<div class="flex-grow flex-nowrap flex items-center">
				<div class="text-base">{{ jobRun.name }}</div>
			</div>
			<ShowHideButton
				v-if="jobRun.tasks.length > 0"
				v-model="isShowingTasks"
				:label="jobRun.tasks.length + ' Tasks'"
				class="mr-4 bg-slate-600 text-slate-200"
			/>
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
				<AiTokenUsageButton :usage="jobRun.usage" />
			</div>
		</div>
		<div v-if="isShowingTasks" class="mt-4 w-full">
			<WorkflowTaskCard
				v-for="task in jobRun.tasks"
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
import WorkflowTaskCard from "@/components/Modules/Workflows/WorkflowRuns/WorkflowTaskCard";
import { ShowHideButton } from "@/components/Shared";
import AiTokenUsageButton from "@/components/Shared/Buttons/AiTokenUsageButton";
import { WorkflowJobRun, WorkflowRun } from "@/types/workflows";
import { FaSolidArrowsRotate as RestartIcon } from "danx-icon";
import { computed, ref } from "vue";

const props = defineProps<{
	workflowRun: WorkflowRun;
	jobRun: WorkflowJobRun;
}>();

const restartJobAction = getAction("restart-job");

const workflowStatus = computed(() => WORKFLOW_STATUS.resolve(props.jobRun.status));
const isShowingTasks = ref(false);
</script>

<style lang="scss" scoped>
.task-menu-item {
	@apply rounded-t p-2 cursor-pointer opacity-70;

	&:hover, &.is-active {
		@apply opacity-100;
	}
}
</style>
