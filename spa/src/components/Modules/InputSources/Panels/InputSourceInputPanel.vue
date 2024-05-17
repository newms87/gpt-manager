<template>
	<div class="p-6">
		<RenderedForm
			v-if="input.files"
			v-model:values="input"
			empty-value=""
			:form="workflowForm"
			:saving="updateAction.isApplying"
			:saved-at="inputSource.updated_at"
			@update:values="updateAction.trigger(inputSource, input)"
		/>
	</div>
</template>
<script setup lang="ts">
import MarkdownEditor from "@/components/MardownEditor/MarkdownEditor";
import { getAction } from "@/components/Modules/InputSources/inputSourceActions";
import { InputSource } from "@/types/input-sources";
import { MultiFileField, RenderedForm } from "quasar-ui-danx";
import { Form } from "quasar-ui-danx/types";
import { h, ref, watch } from "vue";

const props = defineProps<{
	inputSource: InputSource,
}>();

const updateAction = getAction("update-debounced");
const input = ref({
	content: props.inputSource.content,
	files: props.inputSource.files
});

// Load the files/content if they have been loaded after the component is mounted
watch(() => props.inputSource.files, () => {
	if (!input.value.files) {
		input.value.files = props.inputSource.files;
		input.value.content = props.inputSource.content;
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
