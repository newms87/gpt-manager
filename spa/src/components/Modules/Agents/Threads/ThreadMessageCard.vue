<template>
	<div class="overflow-hidden">
		<div class="bg-slate-500 rounded flex items-center">
			<div>
				<QBtn
					:disable="readonly"
					@click="updateAction.trigger(message, {role: message.role === 'user' ? 'assistant' : 'user'})"
				>
					<div class="rounded-full p-1" :class="avatar.class">
						<component :is="avatar.icon" class="w-3 text-slate-300" :class="avatar.iconClass" />
					</div>
				</QBtn>
			</div>
			<div class="font-bold text-slate-400 ml-1 flex-grow">
				<EditOnClickTextField
					:readonly="readonly"
					editing-class="bg-slate-600"
					:model-value="message.title || fDateTime(message.created_at)"
					@update:model-value="updateDebouncedAction.trigger(message, {title: $event})"
				/>
			</div>
			<div class="text-slate-300">
				<QBtn class="mr-2" @click="showFiles = !showFiles">
					<AddImageIcon class="w-4" />
				</QBtn>
				<TrashButton
					v-if="!readonly"
					:saving="deleteAction.isApplying"
					class="mr-2"
					@click.stop="deleteAction.trigger(message)"
				/>
				<QBtn
					v-if="!readonly"
					:saving="resetToMessageAction.isApplying"
					class="mr-2 text-red-900"
					@click.stop="resetToMessageAction.trigger(thread, { message_id: message.id })"
				>
					<ResetToHereIcon class="w-4" />
				</QBtn>
			</div>
		</div>

		<div class="text-sm flex-grow mt-3">
			<MarkdownEditor
				v-model="markdownContent"
				:readonly="readonly"
				editor-class="text-slate-200"
				@update:model-value="updateDebouncedAction.trigger(message, {content})"
			/>
			<template v-if="dataContent">
				<div class="text-amber-800 text-sm font-bold mt-3 mb-2">Data Content (read only)</div>
				<MarkdownEditor
					readonly
					class="pb-3"
					:model-value="dataContent"
				/>
			</template>
			<template v-if="showFiles">
				<MultiFileField
					v-model="files"
					:readonly="readonly"
					@update:model-value="saveFilesAction.trigger(message, { ids: files.map(f => f.id) })"
				/>
			</template>
		</div>
	</div>
</template>
<script setup lang="ts">
import MarkdownEditor from "@/components/MardownEditor/MarkdownEditor";
import { getAction as getThreadAction } from "@/components/Modules/Agents/Threads/threadActions";
import { getAction } from "@/components/Modules/Agents/Threads/threadMessageActions";
import TrashButton from "@/components/Shared/Buttons/TrashButton";
import { AgentThread, ThreadMessage } from "@/types/agents";
import {
	FaRegularUser as UserIcon,
	FaSolidArrowsRotate as ResetToHereIcon,
	FaSolidImage as AddImageIcon,
	FaSolidRobot as AssistantIcon,
	FaSolidToolbox as ToolIcon
} from "danx-icon";
import { EditOnClickTextField, fDateTime, fMarkdownJSON, MultiFileField } from "quasar-ui-danx";
import { UploadedFile } from "quasar-ui-danx/types";
import { computed, ref } from "vue";

const props = defineProps<{
	message: ThreadMessage;
	thread: AgentThread,
	readonly?: boolean;
}>();

const content = ref(props.message.content);
const files = ref<UploadedFile[]>(props.message.files || []);
const markdownContent = computed({
	get: () => fMarkdownJSON(content.value),
	set: (value: string) => {
		content.value = value;
	}
});
const dataContent = computed<string>(() => fMarkdownJSON(props.message.data) || "");

const showFiles = ref(files.value.length > 0);

const avatar = computed<{
	icon: any;
	class: string;
	iconClass?: string;
}>(() => {
	switch (props.message.role) {
		case "user":
			return { icon: UserIcon, class: "bg-lime-800" };
		case "assistant":
			return { icon: AssistantIcon, class: "bg-sky-800", iconClass: "w-4" };
		case "tool":
			return { icon: ToolIcon, class: "bg-indigo-800", iconClass: "text-amber-700" };
		default:
			return { icon: UserIcon, class: "bg-red-800" };
	}
});

const deleteAction = getAction("delete");
const resetToMessageAction = getThreadAction("reset-to-message");
const updateAction = getAction("update");
const saveFilesAction = getAction("save-files");
const updateDebouncedAction = getAction("updateDebounced");
</script>
