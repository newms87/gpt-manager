<template>
	<div class="flex flex-nowrap items-stretch">
		<WorkflowStatusPill
			:status="runner.status"
			class="rounded-r-none w-28"
			:status-class="statusClass + ' ' + padding"
			:inverse="inverse"
			:restart="restart"
			@restart="$emit('restart')"
		/>
		<ElapsedTimePill
			:timer-class="timerClass + ' rounded-l-none flex items-center justify-center'"
			:start="runner.started_at"
			:end="runner.failed_at || runner.completed_at || runner.timeout_at"
		/>
	</div>
</template>
<script setup lang="ts">
import { ElapsedTimePill, WorkflowStatusPill } from "@/components/Modules/Workflows/Shared";
import { TaskRunner } from "@/types";

export interface WorkflowStatusTimerPillProps {
	runner: TaskRunner;
	restart?: boolean;
	inverse?: boolean,
	statusClass?: string;
	timerClass?: string;
	padding?: string;
}

defineEmits(["restart"]);
withDefaults(defineProps<WorkflowStatusTimerPillProps>(), {
	statusClass: "rounded-xl",
	padding: "py-1.5",
	timerClass: "bg-slate-900 w-28 rounded-r-xl"
});
</script>
