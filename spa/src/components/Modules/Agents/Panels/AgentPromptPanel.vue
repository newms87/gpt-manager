<template>
	<div class="p-6">
		<AgentDirectiveField :agent="agent" />
	</div>
</template>
<script setup lang="ts">
import { Agent } from "@/types/agents";
import { AgentDirectiveField } from "../Fields";

defineProps<{
	agent: Agent,
}>();
</script>
