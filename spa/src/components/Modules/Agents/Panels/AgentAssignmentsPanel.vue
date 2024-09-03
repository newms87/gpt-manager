<template>
	<div class="p-6">
		<ListTransition>
			<template v-for="assignment in agent.assignments" :key="assignment.id">
				<QSeparator class="bg-slate-200" />
				<WorkflowAssignmentItem
					:assignment="assignment"
					context="agent"
					:unassign-action="unassignAgentAction"
				/>
			</template>
		</ListTransition>
	</div>
</template>
<script setup lang="ts">
import { dxAgent } from "@/components/Modules/Agents";
import WorkflowAssignmentItem from "@/components/Modules/Workflows/WorkflowJobs/WorkflowAssignmentItem";
import { Agent } from "@/types/agents";
import { ListTransition } from "quasar-ui-danx";

defineProps<{
	agent: Agent,
}>();

const unassignAgentAction = dxAgent.getAction("unassign-agent");
</script>
