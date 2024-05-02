<template>
	<div class="bg-slate-600 rounded overflow-hidden">
		<div class="bg-violet-950 flex items-center p-1">
			<div class="rounded-full p-1" :class="avatar.class">
				<component :is="avatar.icon" class="w-2 text-slate-300" />
			</div>
			<div class="font-bold text-slate-400 ml-3">{{ message.title }}</div>
		</div>

		<div class="text-sm flex-grow">
			<TextField v-model="text" type="textarea" autogrow />
		</div>
	</div>
</template>
<script setup lang="ts">
import { FaRegularUser as UserIcon, FaSolidRobot as AssistantIcon } from "danx-icon";
import { TextField } from "quasar-ui-danx";
import { computed, ref } from "vue";

const props = defineProps<{
	message: ThreadMessage;
}>();
const text = ref(props.message.content);
const avatar = computed(() => ({
	icon: props.message.role === "user" ? UserIcon : AssistantIcon,
	class: props.message.role === "user" ? "bg-lime-800" : "bg-sky-800"
}));
</script>
