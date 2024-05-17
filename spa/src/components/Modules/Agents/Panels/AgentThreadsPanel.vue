<template>
	<div class="p-6">
		<QBtn
			class="text-lg w-full mb-5 transition-all"
			:class="{'bg-lime-800 text-slate-300': !activeThread, 'bg-sky-800 text-slate-200': !!activeThread}"
			:loading="createThreadAction.isApplying"
			:disable="createThreadAction.isApplying"
			@click="activeThread ? (activeThread = null) : createThreadAction.trigger(props.agent)"
		>
			<template v-if="activeThread">
				<CloseIcon class="w-4 mr-3" />
				Close Thread
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
import { getAction } from "@/components/Modules/Agents/agentActions";
import ThreadCard from "@/components/Modules/Agents/Threads/ThreadCard";
import { Agent, AgentThread } from "@/types/agents";
import { FaRegularMessage as CreateIcon, FaSolidArrowLeft as CloseIcon } from "danx-icon";
import { ListTransition } from "quasar-ui-danx";
import { computed, shallowRef } from "vue";

const props = defineProps<{
	agent: Agent,
}>();

const activeThread = shallowRef<AgentThread | null>(null);
const visibleThreads = computed(() => activeThread.value ? [activeThread.value] : props.agent.threads);

const createThreadAction = getAction("create-thread");
</script>
