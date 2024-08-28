<template>
	<div>
		<div class="mb-4">Directives</div>

		<ListTransition
			name="fade-down-list"
			data-drop-zone="column-list"
		>
			<ListItemDraggable
				v-for="(agentDirective, index) in agent.directives"
				:key="agentDirective.id"
				:list-items="agent.directives"
				drop-zone="column-list"
				:class="{'rounded-b-lg': index === agent.directives.length - 1}"
				show-handle
				handle-class="px-2"
				@update:list-items="onListChange"
			>
				<AgentDirectiveCard
					:agent-directive="agentDirective"
					class="my-2"
					:is-removing="removeDirectiveAction.isApplying"
					@remove="removeDirectiveAction.trigger(agent, { id: agentDirective.directive.id })"
				/>
			</ListItemDraggable>
		</ListTransition>


		<div class="flex items-stretch flex-nowrap mt-4">
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
import { ListItemDraggable, ListTransition, SelectField } from "quasar-ui-danx";

const props = defineProps<{
	agent: Agent,
}>();

const saveDirectiveAction = getAction("save-directive");
const updateDirectivesAction = getAction("update-directives");
const removeDirectiveAction = getAction("remove-directive");
const createDirectiveAction = getDirectiveAction("create", { onFinish: AgentController.loadFieldOptions });

async function onCreateDirective() {
	const { item: directive } = await createDirectiveAction.trigger();

	if (directive) {
		await addAgentDirective(directive.id);
	}
}

async function addAgentDirective(id) {
	await saveDirectiveAction.trigger(props.agent, { id });
}

function onListChange(directives) {
	const updatedDirectives = directives.map((directive, index) => ({
		...directive,
		position: index
	}));
	updateDirectivesAction.trigger(props.agent, { directives: updatedDirectives });

}
</script>
