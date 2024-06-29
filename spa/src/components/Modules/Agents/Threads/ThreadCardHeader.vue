<template>
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
			v-model="active"
			:label="thread.messages.length + ' messages'"
			class="bg-slate-700 text-slate-300 py-2 mr-3"
		/>
		<ShowHideButton
			v-model="showLogs"
			class="bg-slate-700 text-slate-300 !p-[.7rem] mr-3"
			:show-icon="ShowLogsIcon"
			:hide-icon="HideLogsIcon"
			icon-class="w-5"
		/>
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
</template>

<script setup lang="ts">
import { getAction } from "@/components/Modules/Agents/Threads/threadActions";
import ActionButton from "@/components/Shared/Buttons/ActionButton";
import AiTokenUsageButton from "@/components/Shared/Buttons/AiTokenUsageButton";
import ShowHideButton from "@/components/Shared/Buttons/ShowHideButton";
import { AgentThread } from "@/types/agents";
import { FaSolidFileCircleCheck as ShowLogsIcon, FaSolidFileCircleXmark as HideLogsIcon } from "danx-icon";
import { EditOnClickTextField } from "quasar-ui-danx";

defineEmits(["toggle", "close"]);
defineProps<{
	thread: AgentThread;
}>();

const active = defineModel<boolean>("active", { default: false });
const showLogs = defineModel<boolean>("logs", { default: false });

const runAction = getAction("run");
const stopAction = getAction("stop");
const updateAction = getAction("update");
const deleteAction = getAction("delete");
</script>
