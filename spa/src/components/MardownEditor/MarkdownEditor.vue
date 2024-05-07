<template>
	<div class="dx-markdown-editor">
		<MilkdownProvider>
			<MilkdownEditor v-model="content" :class="editorClass" />
			<MaxLengthCounter :length="content" :max-length="maxLength" />
		</MilkdownProvider>
	</div>
</template>
<script setup lang="ts">
import MilkdownEditor from "@/components/MardownEditor/MilkdownEditor";
import { MilkdownProvider } from "@milkdown/vue";
import { MaxLengthCounter } from "quasar-ui-danx";

const content = defineModel({ type: String });
defineProps<{
	editorClass?: string | object;
	maxLength?: number;
}>();
</script>
