<template>
	<div class="p-6">
		<div v-if="availableTools" class="flex flex-wrap">
			<div
				v-for="tool in availableTools"
				:key="tool"
				class="flex items-center mb-4"
			>
				<QCheckbox
					v-model="selectedTools"
					:val="tool.name"
					:label="tool.name"
					class="font-bold text-xl"
					@update:model-value="onUpdate"
				/>
				<div class="ml-10">{{ tool.description }}</div>
			</div>
		</div>
		<div v-else class="flex justify-center items-center">
			<QInnerLoading class="mr-2" />
			AI Tools are loading...
		</div>
	</div>
</template>
<script setup lang="ts">
import { getAction } from "@/components/Modules/Agents/agentActions";
import { AgentController } from "@/components/Modules/Agents/agentControls";
import { Agent } from "@/types/agents";
import { computed, ref, watch } from "vue";

const props = defineProps<{
	agent: Agent,
}>();

interface AiTool {
	name: string;
	description: string;
	parameters: object;
}

const selectedTools = ref(Array.isArray(props.agent.tools) ? props.agent.tools : []);
watch(() => props.agent, (agent) => {
	selectedTools.value = Array.isArray(agent.tools) ? agent.tools : [];
});

const availableTools = computed<AiTool[]>(() => AgentController.getFieldOptions("aiTools"));
const updateAction = getAction("update");
function onUpdate(value) {
	if (Array.isArray(value)) {
		updateAction.trigger(props.agent, { tools: selectedTools.value });
	} else {
		selectedTools.value = [];
	}
}
</script>
