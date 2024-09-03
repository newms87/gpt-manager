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
import { dxPromptSchema } from "@/components/Modules/Prompts/Schemas";
import { PromptSchema } from "@/types/prompts";
import { ActionForm } from "quasar-ui-danx";
import { computed } from "vue";

defineProps<{
	promptSchema: PromptSchema,
}>();

const form = computed(() => ({ fields: dxPromptSchema.fields }));
const updateAction = dxPromptSchema.getAction("update-debounced");
</script>
