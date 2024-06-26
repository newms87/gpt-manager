<template>
	<div class="py-2">
		<div
			v-for="assignment in job.assignments"
			:key="assignment.id"
			class="py-1 flex items-center"
		>
			<div class="font-bold">{{ assignment.agent.name }}</div>
			<div class="flex-grow ml-2 text-xs">{{ assignment.agent.model }}</div>
			<ActionButton
				:action="unassignAgentAction"
				:target="assignment"
				type="trash"
				class="p-2 hover:bg-indigo-400"
			/>
		</div>
		<ActionButton
			:action="assignAgentAction"
			:target="job"
			:icon="AssignIcon"
			label="Assign Agent"
			class="bg-indigo-700 text-indigo-300 px-4 w-full mt-2"
			icon-class="w-4"
		/>
	</div>
</template>
<script setup lang="ts">
import { getAction } from "@/components/Modules/Workflows/workflowActions";
import ActionButton from "@/components/Shared/Buttons/ActionButton";
import { WorkflowJob } from "@/types/workflows";
import { FaSolidPlugCircleCheck as AssignIcon } from "danx-icon";

defineProps<{
	job: WorkflowJob;
}>();

const assignAgentAction = getAction("assign-agent");
const unassignAgentAction = getAction("unassign-agent");
</script>
