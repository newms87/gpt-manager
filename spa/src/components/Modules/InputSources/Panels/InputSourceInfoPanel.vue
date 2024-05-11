<template>
	<div class="p-6">
		<RenderedForm
			v-model:values="input"
			empty-value=""
			:form="workflowForm"
			:saving="updateAction.isApplying"
			@update:values="updateAction.trigger(inputSource, input)"
		/>
	</div>
</template>
<script setup lang="ts">
import { InputSource } from "@/components/Modules/InputSources/input-sources";
import { getAction } from "@/components/Modules/InputSources/inputSourceActions";
import { RenderedForm, TextField } from "quasar-ui-danx";
import { Form } from "quasar-ui-danx/types";
import { h, ref } from "vue";

const props = defineProps<{
	inputSource: InputSource,
}>();

const updateAction = getAction("update-debounced");
const input = ref({
	name: props.inputSource.name,
	description: props.inputSource.description
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
