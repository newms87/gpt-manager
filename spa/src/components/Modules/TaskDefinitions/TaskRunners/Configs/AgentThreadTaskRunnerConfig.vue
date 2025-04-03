<template>
	<BaseTaskRunnerConfig :task-definition="taskDefinition">
		<AgentConfig
			:new-agent-name="`${taskDefinition.name} Agent`"
			:model-value="taskDefinition.agent"
			@update:model-value="setAgent"
		/>
	</BaseTaskRunnerConfig>
</template>
<script setup lang="ts">
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions";
import { availableAgents, loadAgents } from "@/components/Modules/TaskDefinitions/TaskDefinitionAgents/agentStore";
import AgentConfig from "@/components/Modules/TaskDefinitions/TaskRunners/Configs/AgentConfig";
import { TaskDefinition } from "@/types";
import { storeObject } from "quasar-ui-danx";
import BaseTaskRunnerConfig from "./BaseTaskRunnerConfig";

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

// Immediately load agents
loadAgents();
</script>
