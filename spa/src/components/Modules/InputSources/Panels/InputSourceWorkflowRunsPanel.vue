<template>
	<div class="p-6">
		<WorkflowRunCard
			v-for="workflowRun in inputSource.workflowRuns"
			:key="workflowRun.id"
			:workflow-run="workflowRun"
		/>
	</div>
</template>
<script setup lang="ts">
import { InputSource } from "@/components/Modules/InputSources/input-sources";
import { WorkflowRunCard } from "@/components/Modules/Workflows/WorkflowRuns";

defineProps<{
	inputSource: InputSource;
}>();
</script>
