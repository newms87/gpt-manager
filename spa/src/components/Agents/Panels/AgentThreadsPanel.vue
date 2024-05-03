<template>
	<div class="p-6">
		<QBtn
			class="bg-lime-800 text-slate-300 text-lg w-full mb-5"
			:loading="isSaving"
			:disable="isSaving"
			@click="onCreate"
		>
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
import { performAction } from "@/components/Agents/agentActions";
import ThreadCard from "@/components/Agents/Threads/ThreadCard";
import ThreadMessageList from "@/components/Agents/Threads/ThreadMessageList";
import { FaRegularMessage as CreateIcon } from "danx-icon";
import { PanelsDrawer } from "quasar-ui-danx";
import { ActionPanel } from "quasar-ui-danx/types";
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

const isSaving = ref(false);
async function onCreate() {
	isSaving.value = true;
	await performAction("create-thread", props.agent);
	isSaving.value = false;
}
</script>
