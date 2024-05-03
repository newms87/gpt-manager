<template>
	<div class="p-6">
		<QBtn
			class="text-lg w-full mb-5 transition-all"
			:class="{'bg-lime-800 text-slate-300': !activeThread, 'bg-sky-800 text-slate-200': !!activeThread}"
			:loading="isSaving"
			:disable="isSaving"
			@click="activeThread ? (activeThread = null) : onCreate"
		>
			<template v-if="activeThread">
				<BackIcon class="w-4 mr-3" />
				Back
			</template>
			<template v-else>
				<CreateIcon class="w-4 mr-3" />
				Create Thread
			</template>
		</QBtn>

		<ListTransition>
			<ThreadCard
				v-for="thread in visibleThreads"
				:key="thread.id"
				:thread="thread"
				class="mb-4"
				:active="activeThread?.id === thread.id"
				@open="activeThread = thread"
				@close="activeThread = null"
			/>
		</ListTransition>
	</div>
</template>
<script setup lang="ts">
import { performAction } from "@/components/Agents/agentActions";
import { Agent, AgentThread } from "@/components/Agents/agents";
import ThreadCard from "@/components/Agents/Threads/ThreadCard";
import { FaRegularHandBackFist as BackIcon, FaRegularMessage as CreateIcon } from "danx-icon";
import { ListTransition } from "quasar-ui-danx";
import { computed, ref, shallowRef } from "vue";

const props = defineProps<{
	agent: Agent,
}>();

const activeThread = shallowRef<AgentThread | null>(null);
const visibleThreads = computed(() => activeThread.value ? [activeThread.value] : props.agent.threads);

const isSaving = ref(false);
async function onCreate() {
	isSaving.value = true;
	await performAction("create-thread", props.agent);
	isSaving.value = false;
}
</script>
