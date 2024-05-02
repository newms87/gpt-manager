<template>
	<div class="p-6">
		<QBtn class="bg-lime-800 text-slate-300 text-lg w-full mb-5" @click="performAction('create-thread', agent)">
			<CreateIcon class="w-4 mr-3" />
			Create Thread
		</QBtn>
		<ThreadCard
			v-for="thread in agent.threads"
			:key="thread.id"
			:thread="thread"
			class="mb-4"
			@open="activeThread = thread.id"
		/>
		<PanelsDrawer
			v-if="activeThread"
			v-model="activeThread"
			:title="activeThreadPanel.label"
			:panels="threadPanels"
			panels-class="w-[60rem]"
			@close="activeThread = null"
		/>
	</div>
</template>
<script setup lang="ts">
import { performAction } from "@/components/Agents/agentsActions";
import ThreadCard from "@/components/Agents/Threads/ThreadCard";
import ThreadMessageList from "@/components/Agents/Threads/ThreadMessageList";
import { FaRegularMessage as CreateIcon } from "danx-icon";
import { ActionPanel, PanelsDrawer } from "quasar-ui-danx";
import { computed, h, ref } from "vue";

const props = defineProps<{
	agent: Agent,
}>();

const activeThread = ref<number | null>(null);
const activeThreadPanel = computed<ActionPanel>(() => threadPanels.value.find(panel => panel.name === activeThread.value));
const threadPanels = computed<ActionPanel[]>(() => props.agent.threads.map(thread => ({
	name: thread.id,
	label: thread.name,
	vnode: () => h(ThreadMessageList, { thread })
})));
</script>
