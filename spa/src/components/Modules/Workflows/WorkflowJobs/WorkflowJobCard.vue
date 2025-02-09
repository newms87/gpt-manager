<template>
	<QCard class="bg-slate-700 text-slate-300 rounded-lg overflow-hidden">
		<div>
			<div class="flex items-center flex-nowrap bg-sky-950 text-slate-300">
				<div class="flex-grow">
					<EditOnClickTextField
						:readonly="isTool"
						:model-value="job.name"
						class="hover:bg-sky-900 text-lg"
						@update:model-value="updateJobDebouncedAction.trigger(job, { name: $event})"
					/>
				</div>
				<div v-if="job.dependencies.length > 0" class="whitespace-nowrap ml-4">
					{{ job.dependencies.length }} Dependencies
				</div>
				<div v-if="!isTool" class="whitespace-nowrap ml-4">
					<div v-if="job.assignments.length > 0">{{ job.assignments.length }} Assignments</div>
					<div v-else class="text-red-600 flex items-center flex-nowrap">
						<WarningIcon class="w-4 mr-2 -mt-1" />
						No Assignments
					</div>
				</div>
				<ShowHideButton
					v-model="showTasksExample"
					label="Tasks Preview"
					class="ml-4 bg-sky-700 text-slate-300 rounded"
				/>
				<ShowHideButton
					v-model="showResponseExample"
					label="Response"
					class="ml-4 bg-lime-900 text-slate-300 rounded"
				/>
				<ShowHideButton
					v-model="isEditing"
					label="Edit"
					class="ml-4 bg-slate-700 text-slate-300 rounded"
				/>
				<ActionButton
					:action="deleteJobAction"
					:target="job"
					class="p-4 ml-2"
					type="trash"
				/>
			</div>
		</div>
		<QCardSection v-if="isEditing" class="flex items-stretch flex-nowrap">
			<div class="w-1/2 pr-8">
				<template v-if="isTool">
					<h5>Workflow Tool</h5>
				</template>
				<template v-else>
					<h5 class="mb-4">Agent Assignments</h5>
					<WorkflowJobAssignmentsManager :job="job" />
				</template>
			</div>
			<div class="w-1/2">
				<h5 class="mb-4">Job Dependencies</h5>
				<WorkflowJobDependenciesList :workflow="workflow" :job="job" />
			</div>
		</QCardSection>
		<QCardSection v-if="showTasksExample">
			<h6 class="text-base">Tasks Preview</h6>
			<div class="mt-4">
				<div v-for="(task, index) in job.tasks_preview" :key="index" class="bg-slate-800 p-4 mb-4 rounded-lg">
					<div class="font-bold">{{ index }}</div>
					<MarkdownEditor readonly format="yaml" :model-value="task" sync-model-changes />
				</div>
			</div>
		</QCardSection>
		<QCardSection v-if="showResponseExample">
			<h6 class="text-base">Response Example</h6>
			<SelectOrCreateField
				class="mt-4"
				:selected="job.responseSchema?.id"
				:options="dxAgent.getFieldOptions('promptSchemas')"
				:loading="createSchemaAction.isApplying"
				@create="onCreateSchema"
				@update:selected="onChangeSchema"
			/>
			<div class="mt-4">
				<MarkdownEditor
					v-if="job.responseSchema"
					:format="job.responseSchema.schema_format"
					:model-value="job.responseSchema.response_example"
					readonly
				/>
			</div>
		</QCardSection>
	</QCard>
</template>
<script setup lang="ts">
import MarkdownEditor from "@/components/MarkdownEditor/MarkdownEditor";
import { dxAgent } from "@/components/Modules/Agents";
import { dxPromptSchema } from "@/components/Modules/Schemas/Schemas";
import { dxWorkflow } from "@/components/Modules/Workflows";
import WorkflowJobAssignmentsManager from "@/components/Modules/Workflows/WorkflowJobs/WorkflowJobAssignmentsManager";
import WorkflowJobDependenciesList from "@/components/Modules/Workflows/WorkflowJobs/WorkflowJobDependenciesList";
import { ActionButton } from "@/components/Shared";
import { Workflow, WorkflowJob } from "@/types/workflows";
import { FaSolidTriangleExclamation as WarningIcon } from "danx-icon";
import { EditOnClickTextField, SelectOrCreateField, ShowHideButton } from "quasar-ui-danx";
import { nextTick, onMounted, ref } from "vue";

const props = defineProps<{
	job: WorkflowJob;
	workflow: Workflow;
	isTool?: boolean;
}>();

const isEditing = ref(false);
const showTasksExample = ref(false);
const showResponseExample = ref(false);
const updateJobAction = dxWorkflow.getAction("update-job-debounced");
const updateJobDebouncedAction = dxWorkflow.getAction("update-job-debounced");
const updatePromptSchemaAction = dxPromptSchema.getAction("update-debounced");
const createSchemaAction = dxPromptSchema.getAction("create");
const deleteJobAction = dxWorkflow.getAction("delete-job");

onMounted(dxAgent.loadFieldOptions);

async function onCreateSchema() {
	const { item: promptSchema } = await createSchemaAction.trigger();

	if (promptSchema) {
		await updateJobAction.trigger(props.job, { response_schema_id: promptSchema.id });
	}
}

async function onChangeSchema(response_schema_id) {
	await updateJobAction.trigger(props.job, { response_schema_id });
	showResponseExample.value = false;
	await nextTick(() => {
		showResponseExample.value = true;
	});
}
</script>
