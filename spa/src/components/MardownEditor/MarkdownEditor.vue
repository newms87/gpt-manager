<template>
	<div class="dx-markdown-editor" :class="{'dx-markdown-json': forceJson, 'dx-markdown-json-invalid': isInvalidJson}">
		<FieldLabel v-if="label" class="mb-2 text-sm" :label="label">
			{{ label }}
		</FieldLabel>
		<MilkdownProvider>
			<MilkdownEditor
				v-if="!isRaw"
				v-model.trim="rawContent"
				:class="editorClass"
				:readonly="readonly"
				@focusout="refreshEditor"
				@update:model-value="updateContent"
			/>
			<TextField
				v-else
				v-model.trim="rawContent"
				:readonly="readonly"
				type="textarea"
				autogrow
				@update:model-value="updateContent"
			/>
			<div class="markdown-footer flex items-center justify-end w-full mt-1 px-2">
				<div class="text-sm mr-4">
					<a v-if="isRaw" @click="isRaw = false">Markdown</a>
					<a v-else @click="isRaw = true">raw</a>
				</div>
				<MaxLengthCounter v-if="maxLength" :length="contentLength" :max-length="maxLength" />
			</div>
		</MilkdownProvider>
	</div>
</template>
<script setup lang="ts">
import MilkdownEditor from "@/components/MardownEditor/MilkdownEditor";
import { MilkdownProvider } from "@milkdown/vue";
import { FieldLabel, fMarkdownJSON, MaxLengthCounter, parseMarkdownJSON, TextField } from "quasar-ui-danx";
import { computed, nextTick, onBeforeMount, ref, watch } from "vue";

const content = defineModel<string | object>();
const rawContent = ref<string | object>();

onBeforeMount(() => {
	rawContent.value = formatContent(content.value);
});
function updateContent(value: string) {
	value = value.trim();
	if (!props.forceJson) {
		return content.value = value;
	}

	const validJson = parseMarkdownJSON(value);
	if (validJson !== undefined) {
		content.value = validJson;
	}
}
function formatContent(value) {
	return (props.forceJson || typeof value === "object") ? fMarkdownJSON(value) : value + "";
}

const isRaw = defineModel("isRaw", { type: Boolean, default: false });
const isInvalidJson = computed(() => props.forceJson && rawContent.value && !parseMarkdownJSON(rawContent.value));
const props = defineProps<{
	editorClass?: string | object;
	maxLength?: number;
	readonly?: boolean;
	label?: string;
	forceJson?: boolean;
	syncModelChanges?: boolean;
}>();

const contentLength = computed(() => typeof content.value === "string" ? content.value.length : JSON.stringify(content.value).length);
function refreshEditor() {
	if (props.forceJson && !isInvalidJson.value) {
		rawContent.value = formatContent(content.value);
	}

	// Milkdown editor is challenging to get to be reactive, so as a quick workaround,
	// just rebuild the editor when the content changes
	if (!isRaw.value) {
		isRaw.value = true;
		nextTick(() => {
			isRaw.value = false;
		});
	}
}

// Watch for changes in the content and update the model if the syncModelChanges prop is set
if (props.syncModelChanges) {
	watch(() => content.value, () => {
		rawContent.value = formatContent(content.value);
		refreshEditor();
	});
}
</script>
