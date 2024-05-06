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
import { AgentController } from "@/components/Agents/agentControls";
import { Agent } from "@/components/Agents/agents";
import { computed, ref } from "vue";

const props = defineProps<{
	agent: Agent,
}>();

defineEmits(["change"]);

interface AiTool {
	name: string;
	description: string;
	parameters: object;
}

const selectedTools = ref(props.agent.tools);
const availableTools = computed<AiTool[]>(() => AgentController.getFieldOptions("aiTools"));
</script>
