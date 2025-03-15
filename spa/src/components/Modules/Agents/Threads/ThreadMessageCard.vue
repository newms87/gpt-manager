<template>
	<div class="overflow-hidden rounded" :class="avatar.messageClass">
		<div class="flex items-center">
			<div>
				<QBtn
					:disable="readonly"
					@click="updateAction.trigger(message, {role: nextRole[message.role] ?? 'user'})"
				>
					<div class="rounded-full p-1 w-6 h-6 flex items-center justify-center" :class="avatar.class">
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
			<div class="text-xs text-slate-400 mr-2 whitespace-nowrap">
				{{ fDateTime(message.timestamp) }}
			</div>
			<ListTransition class="text-slate-300">
				<ShowHideButton v-model="showMessage" :name="'thread-message-' + message.id" />
				<ShowHideButton
					v-if="isUserMessage"
					v-model="showFiles"
					:name="'thread-files-' + message.id"
					class="mr-2"
					:label="files.length || 0"
					:show-icon="AddImageIcon"
					:hide-icon="HideImageIcon"
					tooltip="Show / Hide Images"
				/>
				<ShowHideButton
					v-model="showMetaFields"
					name="show-meta-fields"
					:show-icon="ToggleMetaFieldsIcon"
					:hide-icon="ToggleMetaFieldsIcon"
					class="bg-transparent"
					:color="showMetaFields ? 'sky-invert' : ''"
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
						v-if="thread"
						:action="resetToMessageAction"
						:target="thread"
						:input="{ message_id: message.id }"
						type="refresh"
						class="mr-2"
						tooltip="Reset messages to here"
					/>
				</template>
			</ListTransition>
		</div>

		<template v-if="showMessage">
			<QSeparator class="bg-slate-500 mx-3" />
			<div class="text-sm flex-grow m-3">
				<MarkdownEditor
					v-model="content"
					sync-model-changes
					:readonly="readonly"
					editor-class="text-slate-200 bg-slate-800 rounded"
					:format="isJSON(content) ? 'yaml' : 'text'"
					@update:model-value="updateDebouncedAction.trigger(message, {content})"
				/>
				<template v-if="message.data">
					<div class="text-sm font-bold mt-3 mb-2">Data Content (read only)</div>
					<MarkdownEditor
						readonly
						:model-value="message.data"
						sync-model-changes
					/>
				</template>
				<div v-if="summary">
					<div class="text-sm font-bold mt-3 mb-2">Summary</div>
					<MarkdownEditor
						v-model="summary"
						:readonly="readonly"
						sync-model-changes
						@update:model-value="updateDebouncedAction.trigger(message, {summary})"
					/>
				</div>
			</div>
		</template>
		<template v-if="showFiles && isUserMessage">
			<MultiFileField
				v-model="files"
				:readonly="readonly"
				@update:model-value="saveFilesAction.trigger(message, { ids: files.map(f => f.id) })"
			/>
		</template>
	</div>
</template>
<script setup lang="ts">
import MarkdownEditor from "@/components/MarkdownEditor/MarkdownEditor";
import { dxAgentThread } from "@/components/Modules/Agents/Threads/config";
import { dxThreadMessage } from "@/components/Modules/Agents/Threads/ThreadMessage/config";
import { AgentThread, AgentThreadMessage } from "@/types";
import {
	FaRegularImage as HideImageIcon,
	FaRegularUser as UserIcon,
	FaSolidFilePen as ToggleMetaFieldsIcon,
	FaSolidImage as AddImageIcon,
	FaSolidRobot as AssistantIcon,
	FaSolidToolbox as ToolIcon
} from "danx-icon";
import {
	ActionButton,
	AnyObject,
	EditOnClickTextField,
	fDateTime,
	isJSON,
	ListTransition,
	MultiFileField,
	ShowHideButton,
	UploadedFile
} from "quasar-ui-danx";
import { computed, ref, watch } from "vue";

const props = defineProps<{
	message: AgentThreadMessage;
	thread?: AgentThread,
	readonly?: boolean;
}>();

const showMetaFields = ref(false);
const content = ref(getFilteredContent());
const summary = ref(props.message.summary);
const files = ref<UploadedFile[]>(props.message.files || []);

const showMessage = ref(true);
const showFiles = ref(files.value.length > 0);
const isUserMessage = computed(() => props.message.role === "user");
const nextRole = {
	user: "assistant",
	assistant: "tool",
	tool: "user"
};
const avatar = computed<{
	icon: object;
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

const deleteAction = dxThreadMessage.getAction("delete");
const resetToMessageAction = dxAgentThread.getAction("reset-to-message");
const updateAction = dxThreadMessage.getAction("update");
const saveFilesAction = dxThreadMessage.getAction("save-files");
const updateDebouncedAction = dxThreadMessage.getAction("update-debounced");

// Property meta filtering

watch(() => showMetaFields.value, () => {
	content.value = getFilteredContent();
});

function getFilteredContent() {
	let content = props.message.content;
	if (!showMetaFields.value && isJSON(content)) {
		if (typeof content === "string") {
			content = JSON.parse(content);
		}

		return filterPropertyMeta(content as AnyObject);
	}

	return content;
}

/**
 *  Recursively searches the property keys of the object and removes the property_meta field from each object
 */
function filterPropertyMeta(content: AnyObject = null) {
	if (!content || typeof content !== "object") return content;
	if (Array.isArray(content)) {
		return content.map(filterPropertyMeta);
	}

	const newContent: AnyObject = {};
	for (const key in content) {
		if (key === "property_meta") continue;
		newContent[key] = filterPropertyMeta(content[key]);
	}
	return newContent;
}
</script>
