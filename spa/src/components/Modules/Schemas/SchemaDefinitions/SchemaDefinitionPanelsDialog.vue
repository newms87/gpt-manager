<template>
	<PanelsDrawer
		:title="promptSchema.name"
		:panels="dxPromptSchema.panels"
		:target="promptSchema"
		position="standard"
		panels-class="w-full"
		@close="$emit('close')"
	/>
</template>

<script lang="ts" setup>
import { dxPromptSchema } from "@/components/Modules/Prompts/SchemaDefinitions";
import { SchemaDefinition } from "@/types";
import { PanelsDrawer } from "quasar-ui-danx";

export interface PromptSchemaPanelsDialogProps {
	promptSchema: SchemaDefinition;
}

defineEmits(["close"]);
const props = defineProps<PromptSchemaPanelsDialogProps>();
dxPromptSchema.routes.detailsAndStore(props.promptSchema);
dxPromptSchema.loadFieldOptions();
</script>
