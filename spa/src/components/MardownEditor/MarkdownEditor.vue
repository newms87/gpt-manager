<template>
	<MilkdownProvider>
		<MilkdownEditor v-model="content" :class="editorClass" />
	</MilkdownProvider>
</template>
<script setup lang="ts">
import MilkdownEditor from "@/components/MardownEditor/MilkdownEditor";
import { MilkdownProvider } from "@milkdown/vue";

const content = defineModel({ type: String });
defineProps<{
	editorClass?: string | object;
}>();
</script>
