<template>
	<div class="dx-markdown-editor">
		<FieldLabel v-if="label" class="mb-2 text-sm" :label="label">
			{{ label }}
		</FieldLabel>
		<MilkdownProvider>
			<MilkdownEditor v-if="!isRaw" v-model.trim="content" :class="editorClass" :readonly="readonly" />
			<TextField v-else v-model.trim="content" :readonly="readonly" type="textarea" autogrow />
			<div class="markdown-footer flex items-center justify-end w-full mt-1 px-2">
				<div class="text-sm mr-4">
					<a v-if="isRaw" @click="isRaw = false">Markdown</a>
					<a v-else @click="isRaw = true">raw</a>
				</div>
				<MaxLengthCounter v-if="maxLength" :length="content?.length || 0" :max-length="maxLength" />
			</div>
		</MilkdownProvider>
	</div>
</template>
<script setup lang="ts">
import MilkdownEditor from "@/components/MardownEditor/MilkdownEditor";
import { MilkdownProvider } from "@milkdown/vue";
import { FieldLabel, MaxLengthCounter, TextField } from "quasar-ui-danx";
import { nextTick, watch } from "vue";

const content = defineModel({ type: String });
const isRaw = defineModel("isRaw", { type: Boolean, default: false });
const props = defineProps<{
	editorClass?: string | object;
	maxLength?: number;
	readonly?: boolean;
	label?: string;
	syncModelChanges?: boolean;
}>();

// Watch for changes in the content and update the model if the syncModelChanges prop is set
if (props.syncModelChanges) {
	watch(() => content.value, () => {
		// Milkdown editor is challenging to get to be reactive, so as a quick workaround,
		// just rebuild the editor when the content changes
		if (!isRaw.value) {
			isRaw.value = true;
			nextTick(() => {
				isRaw.value = false;
			});
		}
	});
}
</script>
