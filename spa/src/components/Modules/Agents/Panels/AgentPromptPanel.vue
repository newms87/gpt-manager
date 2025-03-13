<template>
	<div class="p-6">
		<AgentDirectiveField :agent="agent" />
	</div>
</template>
<script setup lang="ts">
import AgentDirectiveField from "@/components/Modules/Agents/Fields/AgentDirectiveField";
import { Agent } from "@/types/agents";

defineProps<{
	agent: Agent,
}>();
</script>
