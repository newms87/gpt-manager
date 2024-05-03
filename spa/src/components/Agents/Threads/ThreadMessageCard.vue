<template>
	<div class="bg-slate-600 rounded overflow-hidden">
		<div class="bg-sky-800 flex items-center p-1">
			<div>
				<QBtn @click="performAction('update', message, {role: message.role === 'user' ? 'assistant' : 'user'})">
					<div class="rounded-full p-1" :class="avatar.class">
						<component :is="avatar.icon" class="w-3 text-slate-300" />
					</div>
				</QBtn>
			</div>
			<div class="font-bold text-slate-400 ml-3">{{ message.title }}</div>
			<div>
				<QBtn @click="performAction('delete', message)">
					<DeleteIcon class="w-3" />
				</QBtn>
			</div>
		</div>

		<div class="text-sm flex-grow">
			<TextField
				v-model="content"
				type="textarea"
				autogrow
				@update:model-value="performAction('update', message, {content})"
			/>
		</div>
	</div>
</template>
<script setup lang="ts">
import { ThreadMessage } from "@/components/Agents/agents";
import { performAction } from "@/components/Agents/Threads/threadMessageActions";
import { FaRegularUser as UserIcon, FaSolidRobot as AssistantIcon, FaSolidTrash as DeleteIcon } from "danx-icon";
import { TextField } from "quasar-ui-danx";
import { computed, ref } from "vue";

const props = defineProps<{
	message: ThreadMessage;
}>();
const content = ref(props.message.content);
const avatar = computed(() => ({
	icon: props.message.role === "user" ? UserIcon : AssistantIcon,
	class: props.message.role === "user" ? "bg-lime-800" : "bg-sky-800"
}));
</script>
