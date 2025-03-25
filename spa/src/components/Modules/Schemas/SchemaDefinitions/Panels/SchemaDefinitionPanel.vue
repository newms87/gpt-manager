<template>
	<div class="p-6">
		<SchemaEditorToolbox editing editable :model-value="schemaDefinition" />
	</div>
</template>
<script setup lang="ts">
import SchemaEditorToolbox from "@/components/Modules/SchemaEditor/SchemaEditorToolbox";
import { SchemaDefinition } from "@/types";

defineProps<{
	schemaDefinition: SchemaDefinition,
}>();
</script>
