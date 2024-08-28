<template>
	<PanelsDrawer
		:title="promptDirective.name"
		:panels="panels"
		:active-item="promptDirective"
		position="standard"
		panels-class="w-full"
		@close="$emit('close')"
	/>
</template>

<script lang="ts" setup>
import { PromptDirectiveController } from "@/components/Modules/Prompts/Directives/promptDirectiveControls";
import { panels } from "@/components/Modules/Prompts/Directives/promptDirectivePanels";
import { PromptDirectiveRoutes } from "@/routes/promptRoutes";
import { PromptDirective } from "@/types";
import { PanelsDrawer } from "quasar-ui-danx";

export interface PromptDirectivePanelsDialogProps {
	promptDirective: PromptDirective;
}

defineEmits(["close"]);
const props = defineProps<PromptDirectivePanelsDialogProps>();
PromptDirectiveRoutes.detailsAndStore(props.promptDirective);
PromptDirectiveController.loadFieldOptions();
</script>
