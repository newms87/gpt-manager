<template>
	<div class="p-6">
		<ActionForm
			:action="updateAction"
			:target="promptSchema"
			:form="{fields}"
		/>
	</div>
</template>
<script setup lang="ts">
import { getAction } from "@/components/Modules/Prompts/Schemas/config/actions";
import { fields } from "@/components/Modules/Prompts/Schemas/config/formFields";
import { PromptSchema } from "@/types/prompts";
import { ActionForm } from "quasar-ui-danx";

defineProps<{
	promptSchema: PromptSchema,
}>();

const updateAction = getAction("update-debounced");
</script>
