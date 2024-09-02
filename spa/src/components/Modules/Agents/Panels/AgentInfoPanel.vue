<template>
	<div class="p-6">
		<ActionForm
			:action="updateAction"
			:target="agent"
			:form="{fields}"
		/>
	</div>
</template>
<script setup lang="ts">
import { getAction } from "@/components/Modules/Agents/config/actions";
import { fields } from "@/components/Modules/Agents/config/formFields";
import { Agent } from "@/types/agents";
import { ActionForm } from "quasar-ui-danx";

defineProps<{
	agent: Agent,
}>();

const updateAction = getAction("update-debounced");
</script>
