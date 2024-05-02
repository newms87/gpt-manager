<template>
	<div class="py-3 flex items-center">
		<div class="rounded-full p-3" :class="avatar.class">
			<component :is="avatar.icon" class="w-3 text-slate-300" />
		</div>
		<div class="bg-slate-600 p-3 rounded ml-3 text-sm flex-grow">
			<div>{{ message.title }}</div>
		</div>
	</div>
</template>
<script setup lang="ts">
import { FaRegularUser as UserIcon, FaSolidRobot as AssistantIcon } from "danx-icon";
import { computed } from "vue";

const props = defineProps<{
	message: ThreadMessage;
}>();
const avatar = computed(() => ({
	icon: props.message.role === "user" ? UserIcon : AssistantIcon,
	class: props.message.role === "user" ? "bg-lime-800" : "bg-sky-800"
}));
</script>
