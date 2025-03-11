<template>
	<div>
		<ActionButton
			color="green"
			:action="addAgentAction"
			:target="taskDefinition"
			type="create"
			label="Add Agent"
		/>

		<ListTransition class="mt-8">
			<template v-if="!taskDefinition.taskAgents">
				<QSkeleton v-for="i in 2" :key="i" class="h-24" />
			</template>

			<template v-else>
				<template
					v-for="taskAgent in taskDefinition.taskAgents"
					:key="taskAgent.id"
				>
					<TaskDefinitionAgentConfigCard
						:task-definition="taskDefinition"
						:task-definition-agent="taskAgent"
					/>

					<QSeparator class="bg-slate-400 my-4" />
				</template>
			</template>
		</ListTransition>
	</div>
</template>
<script setup lang="ts">
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions";
import TaskDefinitionAgentConfigCard
	from "@/components/Modules/TaskDefinitions/TaskDefinitionAgents/TaskDefinitionAgentConfigCard";
import { TaskDefinition } from "@/types";
import { ActionButton, ListTransition } from "quasar-ui-danx";

defineProps<{
	taskDefinition: TaskDefinition;
}>();

const addAgentAction = dxTaskDefinition.getAction("add-agent");
</script>
