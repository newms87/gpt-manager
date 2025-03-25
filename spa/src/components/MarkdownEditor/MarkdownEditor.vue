<template>
	<div
		class="dx-markdown-editor group"
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
				@focusin="isEditing = true"
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
			<div
				class="markdown-footer flex flex-nowrap items-center justify-end w-full -mt-4 relative z-50 opacity-0 group-hover:opacity-100 transition-all"
				:class="{'opacity-100': isEditing}"
			>
				<div class="px-2 bg-slate-800 flex-x rounded-tl">
					<MaxLengthCounter v-if="maxLength" :length="contentLength" :max-length="maxLength" class="mr-4" />
					<div class="text-[.7rem]">
						<a v-if="isRaw" @click="isRaw = false">Markdown</a>
						<a v-else @click="isRaw = true">raw</a>
					</div>
				</div>
			</div>
		</MilkdownProvider>
	</div>
</template>
<script setup lang="ts">
import MilkdownEditor from "@/components/MarkdownEditor/MilkdownEditor";
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
	editorClass: "w-full bg-slate-450 text-slate-800",
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
			return rawContent.value;
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

const isEditing = ref(false);

function refreshEditor() {
	isEditing.value = false;

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
		// Don't update the content if the user is currently editing
		if (isEditing.value) return;

		rawContent.value = formatContent(content.value);
		refreshEditor();
	});
}

watch(() => props.format, () => {
	rawContent.value = formatContent(content.value);
	refreshEditor();
});
</script>
