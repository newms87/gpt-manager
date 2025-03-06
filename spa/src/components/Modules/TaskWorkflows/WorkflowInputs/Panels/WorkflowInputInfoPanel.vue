<template>
	<div class="p-6">
		<RenderedForm
			v-model:values="input"
			empty-value=""
			:form="workflowForm"
			:saving="updateAction.isApplying"
			:saved-at="workflowInput.updated_at"
			@update:values="updateAction.trigger(workflowInput, input)"
		/>
	</div>
</template>
<script setup lang="ts">
import { dxWorkflowInput } from "@/components/Modules/TaskWorkflows/WorkflowInputs";
import { WorkflowInput } from "@/types";
import { Form, RenderedForm, TextField } from "quasar-ui-danx";
import { h, ref } from "vue";

const props = defineProps<{
	workflowInput: WorkflowInput,
}>();

const updateAction = dxWorkflowInput.getAction("update-debounced");
const input = ref({
	name: props.workflowInput.name,
	description: props.workflowInput.description
});

const workflowForm: Form = {
	fields: [
		{
			name: "name",
			vnode: (props) => h(TextField, { ...props, maxLength: 40 }),
			label: "Name",
			required: true
		},
		{
			name: "description",
			vnode: (props) => h(TextField, { ...props, type: "textarea", inputClass: "h-56", maxLength: 512 }),
			label: "Description"
		}
	]
};
</script>
