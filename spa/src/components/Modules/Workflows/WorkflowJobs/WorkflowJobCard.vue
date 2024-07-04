<template>
	<QCard class="bg-indigo-300 text-indigo-600 rounded-lg overflow-hidden form-input-indigo border-indigo-400 border">
		<div>
			<div class="flex items-center flex-nowrap bg-indigo-950 text-indigo-300">
				<div class="flex-grow">
					<EditOnClickTextField
						:readonly="readonly"
						:model-value="job.name"
						class="hover:bg-indigo-900 text-lg"
						@update:model-value="updateJobDebouncedAction.trigger(job, { name: $event})"
					/>
				</div>
				<template v-if="!readonly">
					<div class="pl-4">
						<ShowHideButton
							v-model="showAssignments"
							:label="job.assignments.length + 'Assignments'"
							class="bg-indigo-300 text-indigo-900 rounded"
						/>
					</div>
					<ActionButton
						:action="deleteJobAction"
						:target="job"
						class="p-4"
						type="trash"
					/>
				</template>
			</div>
			<template v-if="!readonly">
				<div class="p-2">
					<div class="py-1 px-2 bg-indigo-900 text-indigo-300 rounded-lg w-52">
						<QCheckbox
							:model-value="!!job.use_input"
							label="Include Workflow Input?"
							@update:model-value="updateJobAction.trigger(job, {use_input: $event})"
						/>
					</div>
					<WorkflowJobDependenciesList :workflow="workflow" :job="job" />
				</div>
			</template>
		</div>
		<MaxHeightTransition max-height="20em">
			<QCardSection v-if="showAssignments" class="pt-0 max-h-[20em] overflow-y-auto">
				<QSeparator class="bg-indigo-900" />
				<WorkflowAssignmentsList :assignments="job.assignments" :unassign-action="unassignAgentAction" />
				<ActionButton
					:action="assignAgentAction"
					:target="job"
					:icon="AssignIcon"
					label="Assign Agent"
					class="bg-indigo-700 text-indigo-300 px-4 w-full mt-2"
					icon-class="w-4"
				/>
			</QCardSection>
		</MaxHeightTransition>
	</QCard>
</template>
<script setup lang="ts">
import { getAction } from "@/components/Modules/Workflows/workflowActions";
import WorkflowJobDependenciesList from "@/components/Modules/Workflows/WorkflowJobs/WorkflowJobDependenciesList";
import { ActionButton, ShowHideButton } from "@/components/Shared";
import { Workflow, WorkflowJob } from "@/types/workflows";
import { FaSolidPlugCircleCheck as AssignIcon } from "danx-icon";
import { EditOnClickTextField, MaxHeightTransition } from "quasar-ui-danx";
import { ref } from "vue";
import WorkflowAssignmentsList from "./WorkflowAssignmentsList";

defineProps<{
	job: WorkflowJob;
	workflow: Workflow;
	readonly?: boolean;
}>();

const showAssignments = ref(false);
const updateJobDebouncedAction = getAction("update-job-debounced");
const updateJobAction = getAction("update-job");
const deleteJobAction = getAction("delete-job");
const assignAgentAction = getAction("assign-agent");
const unassignAgentAction = getAction("unassign-agent");
</script>
