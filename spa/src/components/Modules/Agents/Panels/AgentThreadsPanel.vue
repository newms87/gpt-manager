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
					:active="activeThreadId === thread.id"
					@update:active="setActiveThreadId(activeThreadId === thread.id ? null : thread.id)"
					@close="setActiveThreadId(null)"
				/>
			</template>
		</ListTransition>
	</div>
</template>
<script setup lang="ts">
import { dxAgent } from "@/components/Modules/Agents";
import ThreadCard from "@/components/Modules/Agents/Threads/ThreadCard";
import router from "@/router";
import { Agent } from "@/types/agents";
import { FaRegularMessage as CreateIcon } from "danx-icon";
import { ListTransition } from "quasar-ui-danx";
import { computed, onMounted, ref } from "vue";

const props = defineProps<{
	agent: Agent,
}>();

const activeThreadId = ref<number | null>(null);
const activeThread = computed(() => props.agent.threads?.find((t) => t.id === activeThreadId.value));
const visibleThreads = computed(() => activeThread.value ? [activeThread.value] : props.agent.threads);

const createThreadAction = dxAgent.getAction("create-thread");

function setActiveThreadId(id) {
	activeThreadId.value = id;
	router.push({ name: "agents", params: { id: props.agent.id, panel: "threads", thread_id: id?.toString() } });
}
// On mounted, try to resolve the thread from the URL
onMounted(() => activeThreadId.value = parseInt(router.currentRoute.value.params.thread_id as string));
</script>
