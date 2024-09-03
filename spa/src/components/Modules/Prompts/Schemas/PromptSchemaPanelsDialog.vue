<template>
	<PanelsDrawer
		:title="promptSchema.name"
		:panels="dxPromptSchema.panels.value"
		:target="promptSchema"
		position="standard"
		panels-class="w-full"
		@close="$emit('close')"
	/>
</template>

<script lang="ts" setup>
import { dxPromptSchema } from "@/components/Modules/Prompts/Schemas";
import { PromptSchema } from "@/types";
import { PanelsDrawer } from "quasar-ui-danx";

export interface PromptSchemaPanelsDialogProps {
	promptSchema: PromptSchema;
}

defineEmits(["close"]);
const props = defineProps<PromptSchemaPanelsDialogProps>();
dxPromptSchema.routes.detailsAndStore(props.promptSchema);
dxPromptSchema.loadFieldOptions();
</script>
