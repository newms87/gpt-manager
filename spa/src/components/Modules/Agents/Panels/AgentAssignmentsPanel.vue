<template>
	<div class="p-6">
		<WorkflowAssignmentsList
			v-if="agent.assignments"
			:assignments="agent.assignments"
			:unassign-action="unassignAgentAction"
		/>
	</div>
</template>
<script setup lang="ts">
import { getAction } from "@/components/Modules/Agents/agentActions";
import WorkflowAssignmentsList from "@/components/Modules/Workflows/WorkflowJobs/WorkflowAssignmentsList";
import { Agent } from "@/types/agents";

defineProps<{
	agent: Agent,
}>();

const unassignAgentAction = getAction("unassign-agent");
</script>
