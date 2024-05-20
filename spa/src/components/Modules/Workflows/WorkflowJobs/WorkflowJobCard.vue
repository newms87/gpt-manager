<template>
	<QCard class="bg-indigo-300 text-indigo-600 rounded-lg overflow-hidden form-input-indigo border-indigo-400 border">
		<div>
			<div class="flex items-center flex-nowrap bg-indigo-950 text-indigo-300">
				<div class="flex-grow">
					<EditOnClickTextField
						:model-value="job.name"
						class="hover:bg-indigo-900 text-lg"
						@update:model-value="updateJobDebouncedAction.trigger(job, { name: $event})"
					/>
					<div class="text-sm text-indigo-500">{{ job.description }}</div>
				</div>
				<div class="pl-4">
					<ShowHideButton
						v-model="showAssignments"
						:label="job.assignments.length + 'Assignments'"
						class="bg-indigo-300 text-indigo-900 rounded"
					/>
				</div>
				<TrashButton :saving="deleteJobAction.isApplying" class="p-4" @click="deleteJobAction.trigger(job)" />
			</div>
			<WorkflowJobDependenciesList :workflow="workflow" :job="job" />
		</div>
		<MaxHeightTransition max-height="20em">
			<QCardSection v-if="showAssignments" class="pt-0 max-h-[20em] overflow-y-auto">
				<QSeparator class="bg-indigo-900" />
				<WorkflowAssignmentsList :job="job" />
			</QCardSection>
		</MaxHeightTransition>
	</QCard>
</template>
<script setup lang="ts">
import { getAction } from "@/components/Modules/Workflows/workflowActions";
import WorkflowJobDependenciesList from "@/components/Modules/Workflows/WorkflowJobs/WorkflowJobDependenciesList";
import ShowHideButton from "@/components/Shared/Buttons/ShowHideButton";
import TrashButton from "@/components/Shared/Buttons/TrashButton";
import { Workflow, WorkflowJob } from "@/types/workflows";
import { EditOnClickTextField, MaxHeightTransition } from "quasar-ui-danx";
import { ref } from "vue";
import WorkflowAssignmentsList from "./WorkflowAssignmentsList";

defineProps<{
	job: WorkflowJob;
	workflow: Workflow;
}>();

const showAssignments = ref(false);
const updateJobDebouncedAction = getAction("update-job-debounced");
const deleteJobAction = getAction("delete-job");
</script>
