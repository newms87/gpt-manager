<template>
	<div class="p-6">
		<RenderedForm
			v-model:values="input"
			empty-value=""
			:form="contentSourceForm"
			:saving="updateAction.isApplying"
			@update:values="updateAction.trigger(contentSource, input)"
		/>
	</div>
</template>
<script setup lang="ts">
import { getAction } from "@/components/Modules/ContentSources/contentSourceActions";
import { ContentSource } from "@/types";
import { RenderedForm, TextField } from "quasar-ui-danx";
import { Form } from "quasar-ui-danx/types";
import { h, ref } from "vue";

const props = defineProps<{
	contentSource: ContentSource,
}>();

const updateAction = getAction("update-debounced");
const input = ref({
	name: props.contentSource.name,
	url: props.contentSource.url
});

const contentSourceForm: Form = {
	fields: [
		{
			name: "name",
			vnode: (props) => h(TextField, { ...props, maxLength: 40 }),
			label: "Name",
			required: true
		},
		{
			name: "url",
			vnode: (props) => h(TextField, { ...props, maxLength: 2048 }),
			label: "URL"
		}
	]
};
</script>
