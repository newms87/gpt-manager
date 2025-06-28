<template>
	<div>
		<AgentConfigField
			class="mt-8"
			:new-agent-name="`${taskDefinition.name} Agent`"
			:model-value="taskDefinition.agent"
			@update:model-value="setAgent"
		/>
		<TaskArtifactFiltersField
			v-if="sourceTaskDefinitions?.length > 0"
			class="mt-8"
			:target-task-definition="taskDefinition"
			:source-task-definitions="sourceTaskDefinitions"
		/>
	</div>
</template>
<script setup lang="ts">
import { availableAgents } from "@/components/Modules/Agents/store";
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions";
import { TaskDefinition } from "@/types";
import { storeObject } from "quasar-ui-danx";
import AgentConfigField from "./AgentConfigField";
import TaskArtifactFiltersField from "./TaskArtifactFiltersField";

const props = defineProps<{
	taskDefinition: TaskDefinition;
	sourceTaskDefinitions?: TaskDefinition[];
}>();

const updateTaskDefinitionAction = dxTaskDefinition.getAction("update", {
	optimistic: (action, taskDefinition: TaskDefinition, input) => {
		if (input.agent_id !== undefined) {
			taskDefinition.agent = availableAgents.value.find((a => a.id === input.agent_id));
		}
	}
});

async function setAgent(agent) {
	if (agent?.id === props.taskDefinition.agent.id) return storeObject(agent);

	if (agent) {
		await updateTaskDefinitionAction.trigger(props.taskDefinition, { agent_id: agent.id });
	} else {
		// If agent has been unset, optimistically unset agent in the cache (this is already applied on the backend if the operation was successful)
		storeObject({ ...props.taskDefinition, agent: null });
	}
}
</script>
