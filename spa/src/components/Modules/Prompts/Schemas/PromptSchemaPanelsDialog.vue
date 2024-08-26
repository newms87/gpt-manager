<template>
	<PanelsDrawer
		:title="promptSchema.name"
		:panels="panels"
		:active-item="promptSchema"
		position="standard"
		panels-class="w-full"
		@close="$emit('close')"
	/>
</template>

<script lang="ts" setup>
import { PromptSchemaController } from "@/components/Modules/Prompts/Schemas/promptSchemaControls";
import { panels } from "@/components/Modules/Prompts/Schemas/promptSchemaPanels";
import { PromptSchemaRoutes } from "@/routes/promptRoutes";
import { PromptSchema } from "@/types";
import { PanelsDrawer } from "quasar-ui-danx";

export interface PromptSchemaPanelsDialogProps {
	promptSchema: PromptSchema;
}

defineEmits(["close"]);
const props = defineProps<PromptSchemaPanelsDialogProps>();
PromptSchemaRoutes.detailsAndStore(props.promptSchema);
PromptSchemaController.loadFieldOptions();
</script>
