<template>
	<div class="p-6">
		<WorkflowAssignmentsList v-if="agent.assignments" :assignments="agent.assignments" />
	</div>
</template>
<script setup lang="ts">
import WorkflowAssignmentsList from "@/components/Modules/Workflows/WorkflowJobs/WorkflowAssignmentsList";
import { Agent } from "@/types/agents";

defineProps<{
	agent: Agent,
}>();
</script>
