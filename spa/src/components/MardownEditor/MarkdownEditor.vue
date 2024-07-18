<template>
	<div
		class="dx-markdown-editor"
		:class="{'dx-markdown-code-only': format !== 'text', 'dx-markdown-invalid': validContent === false}"
	>
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
import {
	FieldLabel,
	fMarkdownCode,
	MaxLengthCounter,
	parseMarkdownJSON,
	parseMarkdownYAML,
	TextField
} from "quasar-ui-danx";
import { computed, nextTick, onBeforeMount, ref, watch } from "vue";

export interface MarkdownEditorProps {
	editorClass?: string | object;
	maxLength?: number;
	readonly?: boolean;
	label?: string;
	format?: "text" | "yaml" | "json" | "ts";
	syncModelChanges?: boolean;
}

const props = withDefaults(defineProps<MarkdownEditorProps>(), {
	editorClass: "w-full",
	format: "text",
	maxLength: null,
	label: null
});

const content = defineModel<string | object>();
const rawContent = ref<string>();
const isRaw = defineModel("isRaw", { type: Boolean, default: false });
const validContent = computed(() => {
	if (!rawContent.value) return "";
	switch (props.format) {
		case "json":
			return parseMarkdownJSON(rawContent.value);
		case"yaml":
			return parseMarkdownYAML(rawContent.value);
		case "ts":
			return parseMarkdownJSON(rawContent.value);
		case "text":
		default:
			return rawContent.value;
	}
});
const contentLength = computed(() => typeof content.value === "string" ? content.value.length : JSON.stringify(content.value || "").length);

function updateContent(value: string) {
	value = value.trim();
	if (props.format === "text" || !value) {
		return content.value = value;
	}

	if (validContent.value !== false) {
		content.value = validContent.value;
	}
}

function formatContent(value) {
	const format = (props.format === "text" && value && typeof value === "object") ? "json" : props.format;
	switch (format) {
		case "json":
			return fMarkdownCode(format, value || {});
		case "yaml":
			return fMarkdownCode(format, value || "");
		case "ts":
			return fMarkdownCode(format, value || "");
		case "text":
		default:
			return value ? value + "" : "";

	}
}

function refreshEditor() {
	if (validContent.value) {
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

onBeforeMount(() => {
	rawContent.value = formatContent(content.value);
});

// Watch for changes in the content and update the model if the syncModelChanges prop is set
if (props.syncModelChanges) {
	watch(() => content.value, () => {
		rawContent.value = formatContent(content.value);
		refreshEditor();
	});
}

watch(() => props.format, () => {
	rawContent.value = formatContent(content.value);
	refreshEditor();
});
</script>
