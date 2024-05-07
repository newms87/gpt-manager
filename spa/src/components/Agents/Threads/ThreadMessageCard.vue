<template>
	<div class="bg-slate-600 rounded overflow-hidden">
		<div class="bg-sky-800 flex items-center p-1">
			<div>
				<QBtn @click="updateAction.trigger(message, {role: message.role === 'user' ? 'assistant' : 'user'})">
					<div class="rounded-full p-1" :class="avatar.class">
						<component :is="avatar.icon" class="w-3 text-slate-300" :class="avatar.iconClass" />
					</div>
				</QBtn>
			</div>
			<div class="font-bold text-slate-400 ml-3 flex-grow">{{ message.title }}</div>
			<div>
				<QBtn
					:loading="deleteAction.isApplying"
					:disable="deleteAction.isApplying"
					class="text-slate-300 hover:bg-red-900 shadow-none"
					@click.stop="deleteAction.trigger(message)"
				>
					<DeleteIcon class="w-3" />
				</QBtn>
			</div>
		</div>

		<div class="text-sm flex-grow">
			<MarkdownEditor
				v-model="markdownContent"
				editor-class="text-slate-200 p-3"
				@update:model-value="updateDebouncedAction.trigger(message, {content})"
			/>
			<template v-if="dataContent">
				<div class="px-3 text-amber-600 text-sm font-bold">Data Content (read only)</div>
				<MarkdownEditor
					readonly
					class="px-3 pb-3"
					:model-value="dataContent"
				/>
			</template>
		</div>
	</div>
</template>
<script setup lang="ts">
import { ThreadMessage } from "@/components/Agents/agents";
import { getAction } from "@/components/Agents/Threads/threadMessageActions";
import MarkdownEditor from "@/components/MardownEditor/MarkdownEditor";
import {
	FaRegularUser as UserIcon,
	FaSolidRobot as AssistantIcon,
	FaSolidToolbox as ToolIcon,
	FaSolidTrash as DeleteIcon
} from "danx-icon";
import { computed, ref } from "vue";

const props = defineProps<{
	message: ThreadMessage;
}>();

function isJSON(string: string | object) {
	if (!string) {
		return false;
	}
	if (typeof string === "object") {
		return true;
	}
	try {
		JSON.parse(string);
		return true;
	} catch (e) {
		return false;
	}
}

function fJSON(string: string | object) {
	if (!string) {
		return string;
	}

	try {
		if (typeof string === "object") {
			return JSON.stringify(string, null, 2);
		}
		return JSON.stringify(JSON.parse(string), null, 2);
	} catch (e) {
		return string;
	}
}

function fMarkdownJSON(string: string | object) {
	if (string && isJSON(string)) {
		return `\`\`\`json\n${fJSON(string)}\n\`\`\``;
	}
	return string;
}

const content = ref(props.message.content);
const markdownContent = computed({
	get: () => fMarkdownJSON(content.value),
	set: (value: string) => {
		content.value = value;
	}
});
const dataContent = computed(() => fMarkdownJSON(props.message.data));

const avatar = computed(() => {
	switch (props.message.role) {
		case "user":
			return { icon: UserIcon, class: "bg-lime-800" };
		case "assistant":
			return { icon: AssistantIcon, class: "bg-sky-800", iconClass: "w-4" };
		case "tool":
			return { icon: ToolIcon, class: "bg-amber-300", iconClass: "text-amber-700" };
		default:
			return { icon: UserIcon, class: "bg-red-800" };
	}
});

const deleteAction = getAction("delete");
const updateAction = getAction("update");
const updateDebouncedAction = getAction("updateDebounced");
</script>
