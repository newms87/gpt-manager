<template>
	<div class="p-6">
		<SchemaEditorToolbox editing :model-value="schemaDefinition" />
	</div>
</template>
<script setup lang="ts">
import SchemaEditorToolbox from "@/components/Modules/SchemaEditor/SchemaEditorToolbox";
import { SchemaDefinition } from "@/types/prompts";

defineProps<{
	schemaDefinition: SchemaDefinition,
}>();
</script>
