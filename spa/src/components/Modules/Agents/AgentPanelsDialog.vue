<template>
	<PanelsDrawer
		:title="agent.name"
		:panels="dxAgent.panels"
		:target="agent"
		position="standard"
		panels-class="w-full"
		@close="$emit('close')"
	/>
</template>

<script lang="ts" setup>
import { dxAgent } from "@/components/Modules/Agents";
import { Agent } from "@/types";
import { PanelsDrawer } from "quasar-ui-danx";

export interface AgentPanelsDialogProps {
	agent: Agent;
}

defineEmits(["close"]);
const props = defineProps<AgentPanelsDialogProps>();
dxAgent.routes.details(props.agent);
</script>
