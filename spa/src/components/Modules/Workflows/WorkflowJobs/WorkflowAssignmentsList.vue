<template>
	<div class="py-2">
		<div
			v-for="assignment in job.assignments"
			:key="assignment.id"
			class="py-1 flex items-center"
		>
			<div class="font-bold">{{ assignment.agent.name }}</div>
			<div class="flex-grow ml-2 text-xs">{{ assignment.agent.model }}</div>
			<TrashButton
				:saving="unassignAgentAction.isApplying"
				class="p-2 hover:bg-indigo-400"
				@click="unassignAgentAction.trigger(assignment)"
			/>
		</div>
		<QBtn
			class="bg-indigo-700 text-indigo-300 px-4 w-full mt-2"
			@click="assignAgentAction.trigger(job)"
		>
			<AssignIcon class="w-4 mr-3" />
			Assign Agent
		</QBtn>
	</div>
</template>
<script setup lang="ts">
import { getAction } from "@/components/Modules/Workflows/workflowActions";
import TrashButton from "@/components/Shared/Buttons/TrashButton";
import { WorkflowJob } from "@/types/workflows";
import { FaSolidPlugCircleCheck as AssignIcon } from "danx-icon";

defineProps<{
	job: WorkflowJob;
}>();

const assignAgentAction = getAction("assign-agent");
const unassignAgentAction = getAction("unassign-agent");
</script>
