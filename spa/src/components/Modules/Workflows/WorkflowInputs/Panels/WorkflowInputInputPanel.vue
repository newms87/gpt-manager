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
import { WorkflowInputController } from "@/components/Modules/Workflows/WorkflowInputs/workflowInputControls";
import { WorkflowInput } from "@/types/workflow-inputs";
import { Form, IntegerField, MultiFileField, RenderedForm, SelectField } from "quasar-ui-danx";
import { h, ref, watch } from "vue";

const props = defineProps<{
	workflowInput: WorkflowInput,
}>();

const updateAction = getAction("update-debounced");
const input = ref({
	content: props.workflowInput.content,
	files: props.workflowInput.files,
	team_object_type: props.workflowInput.team_object_type,
	team_object_id: props.workflowInput.team_object_id
});

// Load the files/content if they have been loaded after the component is mounted
watch(() => props.workflowInput.files, () => {
	if (!input.value.files) {
		input.value.files = props.workflowInput.files;
		input.value.content = props.workflowInput.content;
		input.value.team_object_type = props.workflowInput.team_object_type;
		input.value.team_object_id = props.workflowInput.team_object_id;
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
		},
		{
			name: "team_object_type",
			vnode: (props) => h(SelectField, {
				...props,
				options: WorkflowInputController.getFieldOptions("teamObjectTypes")
			}),
			label: "Team Object Type"
		},
		{
			name: "team_object_id",
			vnode: (props) => h(IntegerField, { ...props }),
			label: "Team Object ID"
		}
	]
};
</script>
