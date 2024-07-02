<template>
	<div class="p-6">
		<RenderedForm
			v-if="input.files"
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
import MarkdownEditor from "@/components/MardownEditor/MarkdownEditor";
import { getAction } from "@/components/Modules/Workflows/WorkflowInputs/workflowInputActions";
import { WorkflowInput } from "@/types/workflow-inputs";
import { MultiFileField, RenderedForm } from "quasar-ui-danx";
import { Form } from "quasar-ui-danx";
import { h, ref, watch } from "vue";

const props = defineProps<{
	workflowInput: WorkflowInput,
}>();

const updateAction = getAction("update-debounced");
const input = ref({
	content: props.workflowInput.content,
	files: props.workflowInput.files
});

// Load the files/content if they have been loaded after the component is mounted
watch(() => props.workflowInput.files, () => {
	if (!input.value.files) {
		input.value.files = props.workflowInput.files;
		input.value.content = props.workflowInput.content;
	}
});

const workflowForm: Form = {
	fields: [
		{
			name: "content",
			vnode: (props) => h(MarkdownEditor, { ...props, maxLength: 100000 }),
			label: "Data / Content"
		},
		{
			name: "files",
			vnode: (props) => h(MultiFileField, { ...props }),
			label: "Files"
		}
	]
};
</script>
