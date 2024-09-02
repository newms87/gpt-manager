<template>
	<PanelsDrawer
		:title="promptSchema.name"
		:panels="panels"
		:target="promptSchema"
		position="standard"
		panels-class="w-full"
		@close="$emit('close')"
	/>
</template>

<script lang="ts" setup>
import { dxPromptSchema, panels } from "@/components/Modules/Prompts/Schemas/config";
import { PromptSchemaRoutes } from "@/components/Modules/Prompts/Schemas/config/routes";
import { PromptSchema } from "@/types";
import { PanelsDrawer } from "quasar-ui-danx";

export interface PromptSchemaPanelsDialogProps {
	promptSchema: PromptSchema;
}

defineEmits(["close"]);
const props = defineProps<PromptSchemaPanelsDialogProps>();
PromptSchemaRoutes.detailsAndStore(props.promptSchema);
dxPromptSchema.loadFieldOptions();
</script>
