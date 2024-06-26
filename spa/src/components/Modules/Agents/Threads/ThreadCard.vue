<template>
	<div class="bg-transparent text-slate-300 py-4">
		<div class="flex items-center flex-nowrap cursor-pointer">
			<h5 class="flex-grow overflow-hidden overflow-ellipsis text-no-wrap mr-3">
				<EditOnClickTextField
					:model-value="thread.name"
					class="hover:bg-slate-700 text-base"
					editing-class="bg-slate-600"
					@update:model-value="updateAction.trigger(thread, { name: $event })"
				/>
			</h5>
			<ShowHideButton
				:label="thread.messages.length + ' messages'"
				:model-value="active"
				class="bg-slate-700 text-slate-300 py-2 mr-3"
				@click="$emit('toggle')"
			/>
			<ShowHideButton
				v-model="showLogs"
				class="bg-slate-700 text-slate-300 !p-[.7rem] mr-3"
			>
				<template #default="{isShowing}">
					<HideLogsIcon v-if="isShowing" class="w-5" />
					<ShowLogsIcon v-else class="w-5" />
				</template>
			</ShowHideButton>
			<AiTokenUsageButton :usage="thread.usage" class="py-3 mr-3" />
			<ActionButton
				:action="runAction"
				:target="thread"
				:saving="thread.is_running"
				type="play"
				color="green-invert"
				label="Run"
				class="mr-3 px-3"
			/>
			<ActionButton
				v-if="thread.is_running"
				:action="stopAction"
				:target="thread"
				type="pause"
				color="sky"
				class="p-3"
			/>
			<ActionButton
				v-else
				type="trash"
				color="red"
				:action="deleteAction"
				:target="thread"
				class="p-3"
				@success="$emit('close')"
			/>
		</div>
		<div v-if="!active && thread.summary" class="mt-2">{{ thread.summary }}</div>
		<div v-if="showLogs" class="bg-slate-900 text-slate-400 rounded my-6 p-2">
			<div class="mb-3">
				<a
					v-if="thread.audit_request_id"
					target="_blank"
					:href="$router.resolve({path: `/audit-requests/${thread.audit_request_id}/errors`}).href"
				>
					View Errors
				</a>
			</div>
			<div v-for="(log, index) in thread.logs.split('\n') || ['(Logs Empty)']" :key="index">{{ log }}</div>
		</div>
		<div v-if="active" class="mt-4">
			<ThreadMessageList :thread="thread" />
		</div>
	</div>
</template>

<script setup lang="ts">
import { getAction } from "@/components/Modules/Agents/Threads/threadActions";
import ThreadMessageList from "@/components/Modules/Agents/Threads/ThreadMessageList";
import ActionButton from "@/components/Shared/Buttons/ActionButton";
import AiTokenUsageButton from "@/components/Shared/Buttons/AiTokenUsageButton";
import ShowHideButton from "@/components/Shared/Buttons/ShowHideButton";
import { AgentThread } from "@/types/agents";
import { FaSolidFileCircleCheck as ShowLogsIcon, FaSolidFileCircleXmark as HideLogsIcon } from "danx-icon";
import { EditOnClickTextField } from "quasar-ui-danx";
import { ref } from "vue";

defineEmits(["toggle", "close"]);
defineProps<{
	thread: AgentThread;
	active?: boolean;
}>();

const showLogs = ref(false);

const runAction = getAction("run");
const stopAction = getAction("stop");
const updateAction = getAction("update");
const deleteAction = getAction("delete");
</script>
