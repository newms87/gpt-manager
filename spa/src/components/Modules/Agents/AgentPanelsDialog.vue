<template>
	<PanelsDrawer
		:title="agent.name"
		:panels="panels"
		:target="agent"
		position="standard"
		panels-class="w-full"
		@close="$emit('close')"
	/>
</template>

<script lang="ts" setup>
import { dxAgent } from "@/components/Modules/Agents/config/controls";
import { panels } from "@/components/Modules/Agents/config/panels";
import { AgentRoutes } from "@/components/Modules/Agents/config/routes";
import { Agent } from "@/types";
import { PanelsDrawer } from "quasar-ui-danx";

export interface AgentPanelsDialogProps {
	agent: Agent;
}

defineEmits(["close"]);
const props = defineProps<AgentPanelsDialogProps>();
AgentRoutes.detailsAndStore(props.agent);
dxAgent.loadFieldOptions();
</script>
