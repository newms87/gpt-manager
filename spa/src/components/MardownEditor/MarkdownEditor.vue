<template>
	<div class="dx-markdown-editor">
		<div v-if="label" class="mb-2 text-sm">
			{{ label }}
		</div>
		<MilkdownProvider>
			<MilkdownEditor v-if="!isRaw" v-model.trim="content" :class="editorClass" :readonly="readonly" />
			<TextField v-else v-model.trim="content" type="textarea" autogrow />
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
import { MaxLengthCounter, TextField } from "quasar-ui-danx";

const content = defineModel({ type: String });
const isRaw = defineModel("isRaw", { type: Boolean, default: false });
defineProps<{
	editorClass?: string | object;
	maxLength?: number;
	readonly?: boolean;
	label?: string;
}>();

</script>
