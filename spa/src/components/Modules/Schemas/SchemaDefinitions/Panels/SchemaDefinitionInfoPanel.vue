<template>
	<div class="p-6">
		<ActionForm
			:action="updateAction"
			:target="promptSchema"
			:form="form"
		/>
	</div>
</template>
<script setup lang="ts">
import { dxPromptSchema } from "@/components/Modules/Prompts/SchemaDefinitions";
import { SchemaDefinition } from "@/types/prompts";
import { ActionForm } from "quasar-ui-danx";
import { computed } from "vue";

defineProps<{
	promptSchema: SchemaDefinition,
}>();

const form = computed(() => ({ fields: dxPromptSchema.fields }));
const updateAction = dxPromptSchema.getAction("update-debounced");
</script>
