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
import { dxAgent } from "@/components/Modules/Agents";
import { fields } from "@/components/Modules/Agents/config/fields";
import { Agent } from "@/types";
import { ActionForm } from "quasar-ui-danx";

defineProps<{
	agent: Agent,
}>();

const updateAction = dxAgent.getAction("update-debounced");
</script>
