<template>
	<PanelsDrawer
		:title="promptDirective.name"
		:panels="dxPromptDirective.panels"
		:target="promptDirective"
		position="standard"
		panels-class="w-full"
		@close="$emit('close')"
	/>
</template>

<script lang="ts" setup>
import { dxPromptDirective } from "@/components/Modules/Prompts/Directives";
import { PromptDirective } from "@/types";
import { PanelsDrawer } from "quasar-ui-danx";

export interface PromptDirectivePanelsDialogProps {
	promptDirective: PromptDirective;
}

defineEmits(["close"]);
const props = defineProps<PromptDirectivePanelsDialogProps>();
dxPromptDirective.routes.details(props.promptDirective);
dxPromptDirective.loadFieldOptions();
</script>
