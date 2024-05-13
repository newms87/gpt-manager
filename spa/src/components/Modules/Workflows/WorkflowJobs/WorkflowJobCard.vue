<template>
	<QCard class="bg-indigo-300 text-indigo-600 rounded-lg overflow-hidden form-input-indigo">
		<QCardSection>
			<div class="flex items-center flex-nowrap">
				<div class="flex-grow">
					<div class=" font-bold">
						<EditOnClickTextField
							:model-value="job.name"
							class="hover:bg-indigo-200"
							@update:model-value="updateJobDebouncedAction.trigger(job, { name: $event})"
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
				<div class="ml-4">
					<TrashButton :saving="deleteJobAction.isApplying" @click="deleteJobAction.trigger(job)" />
				</div>
			</div>
			<div>
				<div>Depends On</div>
				<SelectField
					:model-value="job.depends_on || []"
					clearable
					multiple
					:options="jobOptions"
					@update:model-value="updateJobAction.trigger(job, {depends_on: $event})"
				/>
			</div>
		</QCardSection>
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
import { Workflow, WorkflowJob } from "@/components/Modules/Workflows/workflows";
import TrashButton from "@/components/Shared/Buttons/TrashButton";
import { EditOnClickTextField, MaxHeightTransition, SelectField } from "quasar-ui-danx";
import { computed, ref } from "vue";
import WorkflowAssignmentsList from "./WorkflowAssignmentsList";

const props = defineProps<{
	job: WorkflowJob;
	workflow: Workflow;
}>();

const showAssignments = ref(false);
const updateJobAction = getAction("update-job");
const updateJobDebouncedAction = getAction("update-job-debounced");
const deleteJobAction = getAction("delete-job");

const jobOptions = computed(() => props.workflow.jobs.filter(job => job.id !== props.job.id).map((job) => ({
	label: job.name,
	value: job.id
})));
</script>
