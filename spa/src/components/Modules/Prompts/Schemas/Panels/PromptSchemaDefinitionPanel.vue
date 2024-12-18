<template>
	<div class="p-6">
		<SchemaEditorToolbox editing :model-value="promptSchema" />
	</div>
</template>
<script setup lang="ts">
import SchemaEditorToolbox from "@/components/Modules/SchemaEditor/SchemaEditorToolbox";
import { PromptSchema } from "@/types/prompts";

defineProps<{
	promptSchema: PromptSchema,
}>();
</script>
