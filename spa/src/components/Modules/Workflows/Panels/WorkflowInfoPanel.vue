<template>
	<div class="p-6">
		<RenderedForm
			v-model:values="input"
			empty-value=""
			:form="workflowForm"
			:saving="updateAction.isApplying"
			@update:values="updateAction.trigger(workflow, input)"
		/>
	</div>
</template>
<script setup lang="ts">
import { dxWorkflow } from "@/components/Modules/Workflows";
import { Workflow } from "@/types/workflows";
import { Form, RenderedForm, TextField } from "quasar-ui-danx";
import { h, ref } from "vue";

const props = defineProps<{
	workflow: Workflow,
}>();

const updateAction = dxWorkflow.getAction("update-debounced");
const input = ref({
	name: props.workflow.name,
	description: props.workflow.description
});

const workflowForm: Form = {
	fields: [
		{
			name: "name",
			vnode: (props) => h(TextField, { ...props, maxLength: 40 }),
			label: "Workflow Name",
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
