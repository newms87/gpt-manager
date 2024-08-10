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
			<WorkflowStatusTimerPill
				:runner="jobRun"
				restart
				@restart="restartJobAction.trigger(workflowRun, {workflow_job_run_id: jobRun.id})"
			/>
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
import { WorkflowStatusTimerPill } from "@/components/Modules/Workflows/Shared";
import { getAction } from "@/components/Modules/Workflows/workflowRunActions";
import WorkflowTaskCard from "@/components/Modules/Workflows/WorkflowRuns/WorkflowTaskCard";
import { ShowHideButton } from "@/components/Shared";
import AiTokenUsageButton from "@/components/Shared/Buttons/AiTokenUsageButton";
import { WorkflowJobRun, WorkflowRun } from "@/types/workflows";
import { ref } from "vue";

defineProps<{
	workflowRun: WorkflowRun;
	jobRun: WorkflowJobRun;
}>();

const restartJobAction = getAction("restart-job");
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
