<template>
	<BaseTaskRunnerConfig :task-definition="taskDefinition">
		<QSeparator class="bg-slate-400 my-4" />
		<AgentThreadInstructions />
		<AgentConfigField
			class="mt-8"
			:new-agent-name="`${taskDefinition.name} Agent`"
			:model-value="taskDefinition.agent"
			@update:model-value="setAgent"
		/>
		<div class="bg-sky-950 p-3 rounded mt-4">
			<div class="font-bold mb-2">Directives</div>
			<TaskDefinitionDirectivesConfigField :task-definition="taskDefinition" />
		</div>
		<SchemaAndFragmentsConfigField class="mt-4" :task-definition="taskDefinition" />
	</BaseTaskRunnerConfig>
</template>
<script setup lang="ts">
import { availableAgents } from "@/components/Modules/Agents/store";
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions";
import { TaskDefinition } from "@/types";
import { storeObject } from "quasar-ui-danx";
import BaseTaskRunnerConfig from "./BaseTaskRunnerConfig";
import { AgentConfigField, SchemaAndFragmentsConfigField, TaskDefinitionDirectivesConfigField } from "./Fields";
import { AgentThreadInstructions } from "./Instructions";

const props = defineProps<{
	taskDefinition: TaskDefinition;
}>();

const updateTaskDefinitionAction = dxTaskDefinition.getAction("update", {
	optimistic: (action, taskDefinition: TaskDefinition, input) => {
		if (input.agent_id !== undefined) {
			taskDefinition.agent = availableAgents.value.find((a => a.id === input.agent_id));
		}
	}
});

async function setAgent(agent) {
	if (agent) {
		await updateTaskDefinitionAction.trigger(props.taskDefinition, { agent_id: agent.id });
	} else {
		// If agent has been unset, optimistically unset agent in the cache (this is already applied on the backend if the operation was successful)
		storeObject({ ...props.taskDefinition, agent: null });
	}
}
</script>
