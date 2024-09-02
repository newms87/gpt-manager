<template>
	<div class="p-6">
		<ActionForm
			:action="updateDebouncedAction"
			:target="promptDirective"
			:form="promptDirectiveForm"
		/>
	</div>
</template>
<script setup lang="ts">
import MarkdownEditor from "@/components/MardownEditor/MarkdownEditor";
import { getAction } from "@/components/Modules/Prompts/Directives/config/actions";
import { PromptDirective } from "@/types/prompts";
import { ActionForm, Form, TextField } from "quasar-ui-danx";
import { h } from "vue";

defineProps<{
	promptDirective: PromptDirective,
}>();

const updateDebouncedAction = getAction("update-debounced");

const promptDirectiveForm: Form = {
	fields: [
		{
			name: "name",
			vnode: (props) => h(TextField, { ...props, maxLength: 255 }),
			label: "Name",
			required: true
		},
		{
			name: "directive_text",
			vnode: (props) => h(MarkdownEditor, {
				...props,
				maxLength: 64000
			}),
			label: "Directive"
		}
	]
};
</script>
