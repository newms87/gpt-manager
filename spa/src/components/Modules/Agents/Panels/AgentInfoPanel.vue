<template>
	<div class="p-6">
		<AgentApiConfigForm :agent="agent" />
	</div>
</template>
<script setup lang="ts">
import AgentApiConfigForm from "@/components/Modules/Agents/Forms/AgentApiConfigForm";
import { Agent } from "@/types";

defineProps<{
	agent: Agent,
}>();
</script>
