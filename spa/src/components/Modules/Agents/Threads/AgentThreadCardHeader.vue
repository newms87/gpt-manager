<template>
	<div class="flex-x cursor-pointer">
		<h5 class="flex-grow overflow-hidden overflow-ellipsis text-no-wrap mr-3">
			<EditOnClickTextField
				:model-value="thread.name"
				class="hover:bg-slate-700 text-base"
				editing-class="bg-slate-600"
				@update:model-value="updateAction.trigger(thread, { name: $event })"
			/>
		</h5>
		<div class="text-xs text-slate-400 mr-2 whitespace-nowrap">
			{{ fDateTime(thread.timestamp) }}
		</div>
		<ShowHideButton
			v-model="active"
			:label="thread.messages.length + ' messages'"
			class="bg-slate-700 text-slate-300 py-2 mr-3"
		/>
		<ThreadMessageControls
			v-if="active"
			:all-messages-expanded="allMessagesExpanded"
			:all-files-expanded="allFilesExpanded"
			@toggle-messages="$emit('toggle-messages')"
			@toggle-files="$emit('toggle-files')"
		/>
		<ShowHideButton
			v-model="showLogs"
			class="bg-slate-700 text-slate-300 !p-[.7rem] mr-3"
			:show-icon="ShowLogsIcon"
			:hide-icon="HideLogsIcon"
			icon-class="w-5"
		/>
		<AiTokenUsageButton v-if="thread.usage" :usage="thread.usage" class="py-3 mr-3" />
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
		<ActionMenu
			v-else
			:actions="dxAgentThread.menuActions"
			:target="thread"
			@success="$event.name === 'delete' ? $emit('close') : null"
		/>
	</div>
</template>

<script setup lang="ts">
import { dxAgentThread } from "@/components/Modules/Agents/Threads/config";
import ThreadMessageControls from "@/components/Modules/Agents/Threads/ThreadMessageControls.vue";
import { AiTokenUsageButton } from "@/components/Shared";
import { AgentThread } from "@/types";
import {
	FaSolidFileCircleCheck as ShowLogsIcon,
	FaSolidFileCircleXmark as HideLogsIcon
} from "danx-icon";
import { ActionButton, ActionMenu, EditOnClickTextField, fDateTime, ShowHideButton } from "quasar-ui-danx";

defineEmits(["toggle", "close", "toggle-messages", "toggle-files"]);
defineProps<{
	thread: AgentThread;
	allMessagesExpanded: boolean;
	allFilesExpanded: boolean;
}>();

const active = defineModel<boolean>("active", { default: false });
const showLogs = defineModel<boolean>("logs", { default: false });

const runAction = dxAgentThread.getAction("run");
const stopAction = dxAgentThread.getAction("stop");
const updateAction = dxAgentThread.getAction("update");
</script>
