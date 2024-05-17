<template>
	<QCard class="bg-slate-400 text-slate-700">
		<QCardSection>
			<div class="flex items-center flex-nowrap cursor-pointer" @click="$emit('open')">
				<h5 class="flex-grow overflow-hidden overflow-ellipsis text-no-wrap mr-3">

					<EditOnClickTextField
						:model-value="thread.name"
						class="hover:bg-slate-300"
						@update:model-value="updateAction.trigger(thread, { name: $event })"
					/>
				</h5>
				<QBtn
					class="text-lime-800 bg-green-200 hover:bg-lime-800 hover:text-green-200 mr-6 px-3"
					:disable="runAction.isApplying"
					:loading="runAction.isApplying"
					@click.stop="runAction.trigger(thread)"
				>
					<div class="flex flex-nowrap items-center">
						<RunIcon class="w-3 mr-2" />
						<div class="text-no-wrap">
							Run ({{ thread.messages.length }} messages)
						</div>
					</div>
				</QBtn>
				<QBtn
					class="text-red-900 hover:bg-red-300 shadow-none"
					:disable="deleteAction.isApplying"
					:loading="deleteAction.isApplying"
					@click.stop="onDelete"
				>
					<DeleteIcon class="w-4" />
				</QBtn>
			</div>
			<div v-if="!active && thread.summary" class="mt-2">{{ thread.summary }}</div>
			<div v-if="active" class="mt-4">
				<ThreadMessageList :thread="thread" />
			</div>
		</QCardSection>
	</QCard>
</template>

<script setup lang="ts">
import { getAction } from "@/components/Modules/Agents/Threads/threadActions";
import ThreadMessageList from "@/components/Modules/Agents/Threads/ThreadMessageList";
import { AgentThread } from "@/types/agents";
import { FaRegularTrashCan as DeleteIcon, FaSolidPlay as RunIcon } from "danx-icon";
import { EditOnClickTextField } from "quasar-ui-danx";

const emit = defineEmits(["open", "close"]);
const props = defineProps<{
	thread: AgentThread;
	active?: boolean;
}>();

const runAction = getAction("run");
const updateAction = getAction("update");
const deleteAction = getAction("delete");

async function onDelete() {
	const result = await deleteAction.trigger(props.thread);
	if (result?.success) {
		emit("close");
	}
}
</script>
