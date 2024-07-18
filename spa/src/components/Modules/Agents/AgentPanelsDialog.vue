<template>
	<PanelsDrawer
		:title="agent.name"
		:panels="panels"
		:active-item="agent"
		position="standard"
		panels-class="w-full"
		@close="$emit('close')"
	/>
</template>

<script lang="ts" setup>
import { AgentController } from "@/components/Modules/Agents/agentControls";
import { panels } from "@/components/Modules/Agents/agentPanels";
import { AgentRoutes } from "@/routes/agentRoutes";
import { Agent } from "@/types";
import { PanelsDrawer } from "quasar-ui-danx";

export interface AgentPanelsDialogProps {
	agent: Agent;
}

defineEmits(["close"]);
const props = defineProps<AgentPanelsDialogProps>();
AgentRoutes.detailsAndStore(props.agent);
AgentController.loadFieldOptions();
</script>
