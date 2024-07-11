<template>
	<div class="p-6 h-full overflow-y-auto">
		<ListTransition class="h-full">
			<QBtn
				v-if="!activeThread"
				class="text-lg w-full mb-5 bg-lime-800 text-slate-300"
				:loading="createThreadAction.isApplying"
				@click="createThreadAction.trigger(props.agent)"
			>
				<CreateIcon class="w-4 mr-3" />
				Create Thread
			</QBtn>

			<template v-for="thread in visibleThreads" :key="thread.id">
				<QSeparator v-if="!activeThread" class="bg-slate-200" />
				<ThreadCard
					:class="{'my-4': !activeThread }"
					:thread="thread"
					:active="activeThread?.id === thread.id"
					@update:active="activeThread = (activeThread?.id === thread.id ? null : thread)"
					@close="activeThread = null"
				/>
			</template>
		</ListTransition>
	</div>
</template>
<script setup lang="ts">
import { getAction } from "@/components/Modules/Agents/agentActions";
import ThreadCard from "@/components/Modules/Agents/Threads/ThreadCard";
import { Agent, AgentThread } from "@/types/agents";
import { FaRegularMessage as CreateIcon } from "danx-icon";
import { ListTransition } from "quasar-ui-danx";
import { computed, shallowRef } from "vue";

const props = defineProps<{
	agent: Agent,
}>();

const activeThread = shallowRef<AgentThread | null>(null);
const visibleThreads = computed(() => activeThread.value ? [activeThread.value] : props.agent.threads);

const createThreadAction = getAction("create-thread");
</script>
