<template>
	<div class="rounded-lg bg-sky-950 text-slate-300 overflow-hidden">
		<div class="flex items-center flex-nowrap">
			<div class="text-base font-bold flex-grow px-2 text-no-wrap text-ellipsis">{{ dependency.depends_on_name }}</div>
			<ShowHideButton
				v-model="isEditing"
				label="Configure"
				:hide-icon="HideConfigureIcon"
				:show-icon="ShowConfigureIcon"
			/>
			<ActionButton :saving="saving" type="trash" class="p-4" @click="$emit('remove')" />
		</div>
		<div v-if="isEditing" class="mb-4">
			<QSeparator class="bg-slate-500 mb-4" />
			<div class="px-4">
				<div class="flex items-center flex-nowrap">
					<div class="mr-2 w-16">Include:</div>
					<SelectField
						v-model="includeFields"
						class="flex-grow"
						:options="dependentFields"
						clearable
						multiple
						placeholder="(All Data)"
						@update:model-value="$emit('update', {...dependency, include_fields: includeFields})"
					/>
				</div>
				<div class="flex items-center flex-nowrap mt-4">
					<div class="mr-2 w-16">Group By:</div>
					<SelectField
						v-model="groupBy"
						class="flex-grow"
						:options="includeFields.length > 0 ? includeFields : dependentFields"
						clearable
						multiple
						placeholder="(No Grouping)"
						@update:model-value="$emit('update', {...dependency, group_by: groupBy})"
					/>
				</div>
				<div class="mt-4">
					<h6 class="text-base">Example Task Grouping</h6>
					<div>

					</div>
				</div>
			</div>
		</div>
	</div>
</template>
<script setup lang="ts">
import { ShowHideButton } from "@/components/Shared";
import ActionButton from "@/components/Shared/Buttons/ActionButton";
import { Workflow, WorkflowJob, WorkflowJobDependency } from "@/types/workflows";
import { FaSolidScrewdriverWrench as HideConfigureIcon, FaSolidWrench as ShowConfigureIcon } from "danx-icon";
import { SelectField } from "quasar-ui-danx";
import { computed, ref } from "vue";

defineEmits(["update", "remove"]);
const props = defineProps<{
	dependency: WorkflowJobDependency;
	job: WorkflowJob;
	workflow: Workflow;
	saving?: boolean;
}>();

const isEditing = ref(false);
const includeFields = ref(props.dependency.include_fields || []);
const groupBy = ref(props.dependency.group_by || []);

const dependentJob = computed(() => props.workflow.jobs.find(job => job.id === props.dependency.depends_on_id));
const dependentJobAgentsWithoutResponseSample = computed(() => dependentJob.value.assignments.filter(assignment => !assignment.agent.response_sample));

/**
 * The list of fields (including nested fields) available amongst all the sample responses for all assigned agents in the job dependencies.
 */
const dependentFields = computed<string[]>(() => {
	const fields = [];
	for (const assignment of dependentJob.value.assignments) {
		if (assignment.agent.response_sample) {
			fields.push(...getNestedFieldList(assignment.agent.response_sample));
		}
	}
	// make fields a unique list
	return [...new Set(fields)];
});

/**
 * A flat list of all fields and nested fields expressed in dot notation
 */
function getNestedFieldList(object) {
	const fields = [];
	for (const fieldName of Object.keys(object)) {
		const fieldValue = object[fieldName];
		fields.push(fieldName);
		if (Array.isArray(fieldValue)) {
			for (const item of fieldValue) {
				if (typeof item === "object") {
					fields.push(...getNestedFieldList(item).map(nestedField => `${fieldName}.*.${nestedField}`));
				}
			}
		} else if (typeof fieldValue === "object") {
			for (const nestedField of getNestedFieldList(fieldValue)) {
				fields.push(`${fieldName}.${nestedField}`);
			}
		}
	}
	return fields;
}
</script>
