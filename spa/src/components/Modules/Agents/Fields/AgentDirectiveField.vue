<template>
	<div>
		<div class="mb-4">Directives</div>

		<AgentDirectiveCard
			v-for="agentDirective in agent.directives"
			:key="agentDirective.id"
			:agent-directive="agentDirective"
			class="mb-4"
		/>

		<div class="flex items-stretch flex-nowrap">
			<SelectField
				class="flex-grow"
				:options="AgentController.getFieldOptions('promptDirectives')"
				@update="addAgentDirective"
			/>
			<QBtn class="bg-green-900 ml-4 w-1/5" :loading="createDirectiveAction.isApplying" @click="onCreateDirective">
				Create
			</QBtn>
		</div>
	</div>
</template>
<script setup lang="ts">
import { getAction } from "@/components/Modules/Agents/agentActions";
import { AgentController } from "@/components/Modules/Agents/agentControls";
import AgentDirectiveCard from "@/components/Modules/Agents/Fields/AgentDirectiveCard";
import { getAction as getDirectiveAction } from "@/components/Modules/Prompts/Directives/promptDirectiveActions";
import { Agent } from "@/types/agents";
import { SelectField } from "quasar-ui-danx";

const props = defineProps<{
	agent: Agent,
}>();

const addDirectiveAction = getAction("save-directive");
const createDirectiveAction = getDirectiveAction("create", { onFinish: AgentController.loadFieldOptions });

async function onCreateDirective() {
	const { item: directive } = await createDirectiveAction.trigger();

	if (directive) {
		await addAgentDirective(directive.id);
	}
}

async function addAgentDirective(id) {
	await addDirectiveAction.trigger(props.agent, { id });
}
</script>
