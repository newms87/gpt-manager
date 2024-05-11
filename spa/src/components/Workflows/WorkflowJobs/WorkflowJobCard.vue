<template>
	<QCard class="bg-indigo-300 text-indigo-600 rounded-lg overflow-hidden">
		<QCardSection class="flex items-center flex-nowrap">
			<div class="flex-grow">
				<div class=" font-bold">
					<EditOnClickTextField
						:model-value="job.name"
						class="hover:bg-indigo-200"
						@update:model-value="updateJobAction.trigger(job, { name: $event})"
					/>
				</div>
				<div class="text-sm text-indigo-500">{{ job.description }}</div>
			</div>
			<div class="px-4">
				{{ job.runs_count }} Runs
			</div>
			<div class="pl-4">
				<QBtn class="bg-indigo-700 text-indigo-300 px-4" @click="showAssignments = !showAssignments">
					{{ job.assignments.length }} Assignments
				</QBtn>
			</div>
		</QCardSection>
		<QCardSection v-if="showAssignments" class="pt-0">
			<QSeparator class="bg-indigo-900" />
			<div
				v-for="assignment in job.assignments"
				:key="assignment.id"
				class="p-4 bg-indigo-200 text-indigo-800 flex items-center"
			>
				<div class="font-bold">{{ assignment.agent.name }}</div>
				<div class="flex-grow ml-2 text-xs">{{ assignment.agent.model }}</div>
				<TrashButton
					:saving="unassignAgentAction.isApplying"
					class="hover:bg-red-300"
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
		</QCardSection>
	</QCard>
</template>
<script setup lang="ts">
import TrashButton from "@/components/Shared/Buttons/TrashButton";
import { getAction } from "@/components/Workflows/workflowActions";
import { WorkflowJob } from "@/components/Workflows/workflows";
import { FaSolidPlugCircleCheck as AssignIcon } from "danx-icon";
import { EditOnClickTextField } from "quasar-ui-danx";
import { ref } from "vue";

defineProps<{
	job: WorkflowJob;
}>();

const showAssignments = ref(false);
const updateJobAction = getAction("update-job");
const assignAgentAction = getAction("assign-agent");
const unassignAgentAction = getAction("unassign-agent");
</script>
