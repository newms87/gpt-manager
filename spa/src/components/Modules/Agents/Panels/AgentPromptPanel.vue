<template>
	<div class="p-6">
		<AgentDirectiveField :agent="agent" />

		<QSeparator class="bg-slate-500 my-8" />

		<AgentSchemaField :agent="agent" />
	</div>
</template>
<script setup lang="ts">
import AgentDirectiveField from "@/components/Modules/Agents/Fields/AgentDirectiveField";
import AgentSchemaField from "@/components/Modules/Agents/Fields/AgentSchemaField";
import { Agent } from "@/types/agents";

defineProps<{
	agent: Agent,
}>();
</script>
