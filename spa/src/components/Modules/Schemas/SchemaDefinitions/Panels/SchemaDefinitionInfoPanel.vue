<template>
	<div class="p-6">
		<ActionForm
			:action="updateAction"
			:target="schemaDefinition"
			:form="form"
		/>
	</div>
</template>
<script setup lang="ts">
import { dxSchemaDefinition } from "@/components/Modules/Schemas/SchemaDefinitions";
import { SchemaDefinition } from "@/types";
import { ActionForm } from "quasar-ui-danx";
import { computed } from "vue";

defineProps<{
	schemaDefinition: SchemaDefinition,
}>();

const form = computed(() => ({ fields: dxSchemaDefinition.fields }));
const updateAction = dxSchemaDefinition.getAction("update-debounced");
</script>
