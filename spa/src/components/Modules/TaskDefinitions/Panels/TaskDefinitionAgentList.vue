<template>
	<div>
		<ListTransition>
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

			<QBtn
				class="mt-4 bg-lime-800 text-slate-300"
				:loading="addAgentAction.isApplying"
				@click="addAgentAction.trigger(taskDefinition)"
			>
				<CreateIcon class="w-4 mr-3" />
				Add Agent
			</QBtn>
		</ListTransition>
	</div>
</template>
<script setup lang="ts">
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions";
import TaskDefinitionAgentConfigCard from "@/components/Modules/TaskDefinitions/Panels/TaskDefinitionAgentConfigCard";
import { TaskDefinition } from "@/types";
import { FaSolidPlus as CreateIcon } from "danx-icon";
import { ListTransition } from "quasar-ui-danx";

defineProps<{
	taskDefinition: TaskDefinition;
}>();

const addAgentAction = dxTaskDefinition.getAction("add-agent");
</script>
