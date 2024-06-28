<template>
	<div class="overflow-hidden rounded" :class="avatar.messageClass">
		<div class="flex items-center">
			<div>
				<QBtn
					:disable="readonly"
					@click="updateAction.trigger(message, {role: nextRole[message.role] ?? 'user'})"
				>
					<div class="rounded-full p-1" :class="avatar.class">
						<component :is="avatar.icon" class="w-3 text-slate-300" :class="avatar.iconClass" />
					</div>
					<QTooltip>Toggle message role</QTooltip>
				</QBtn>
			</div>
			<div class="font-bold text-slate-400 ml-1 mr-2 flex-grow">
				<EditOnClickTextField
					:readonly="readonly"
					editing-class="bg-slate-600"
					:model-value="message.title || fDateTime(message.created_at)"
					@update:model-value="updateDebouncedAction.trigger(message, {title: $event})"
				/>
			</div>
			<div class="text-slate-300">
				<ShowHideButton v-model="showMessage" :name="'thread-message-' + message.id" class="mr-2" />
				<ShowHideButton
					v-model="showFiles"
					:name="'thread-files-' + message.id"
					class="mr-2"
					:show-icon="AddImageIcon"
					:hide-icon="AddImageIcon"
					tooltip="Show / Hide Images"
				/>
				<template v-if="!readonly">
					<ActionButton
						:action="deleteAction"
						:target="message"
						type="trash"
						class="mr-2"
						tooltip="Delete message"
					/>
					<ActionButton
						:action="resetToMessageAction"
						:target="thread"
						:input="{ message_id: message.id }"
						type="refresh"
						class="mr-2"
						tooltip="Reset messages to here"
					/>
				</template>
			</div>
		</div>

		<template v-if="showMessage">
			<QSeparator class="bg-slate-500 mx-3" />
			<div class="text-sm flex-grow m-3">
				<MarkdownEditor
					v-model="markdownContent"
					:readonly="readonly"
					editor-class="text-slate-200"
					@update:model-value="updateDebouncedAction.trigger(message, {content})"
				/>
				<template v-if="dataContent">
					<div class="text-sm font-bold mt-3 mb-2">Data Content (read only)</div>
					<MarkdownEditor
						readonly
						:model-value="dataContent"
					/>
				</template>
			</div>
		</template>
		<template v-if="showFiles">
			<MultiFileField
				v-model="files"
				:readonly="readonly"
				@update:model-value="saveFilesAction.trigger(message, { ids: files.map(f => f.id) })"
			/>
		</template>
	</div>
</template>
<script setup lang="ts">
import MarkdownEditor from "@/components/MardownEditor/MarkdownEditor";
import { getAction as getThreadAction } from "@/components/Modules/Agents/Threads/threadActions";
import { getAction } from "@/components/Modules/Agents/Threads/threadMessageActions";
import ActionButton from "@/components/Shared/Buttons/ActionButton";
import ShowHideButton from "@/components/Shared/Buttons/ShowHideButton";
import { AgentThread, ThreadMessage } from "@/types/agents";
import {
	FaRegularUser as UserIcon,
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

const showMessage = ref(true);
const showFiles = ref(files.value.length > 0);

const nextRole = {
	user: "assistant",
	assistant: "tool",
	tool: "user"
};
const avatar = computed<{
	icon: any;
	class: string;
	iconClass?: string;
	messageClass?: string;
}>(() => {
	switch (props.message.role) {
		case "user":
			return { icon: UserIcon, class: "bg-lime-700", messageClass: "bg-lime-900" };
		case "assistant":
			return { icon: AssistantIcon, class: "bg-sky-600", iconClass: "w-4", messageClass: "bg-sky-800" };
		case "tool":
			return { icon: ToolIcon, class: "bg-indigo-500", messageClass: "bg-indigo-800" };
		default:
			return { icon: UserIcon, class: "bg-red-700", messageClass: "bg-red-900" };
	}
});

const deleteAction = getAction("delete");
const resetToMessageAction = getThreadAction("reset-to-message");
const updateAction = getAction("update");
const saveFilesAction = getAction("save-files");
const updateDebouncedAction = getAction("updateDebounced");
</script>
