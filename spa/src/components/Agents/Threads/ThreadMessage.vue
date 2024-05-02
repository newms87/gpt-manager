<template>
	<div class="p-3 flex items-center">
		<MessageIcon class="w-5 rounded bg-sky-500" />
		<h4>{{ message.title }}</h4>
	</div>
</template>
<script setup lang="ts">
import { FaRegularUser as UserIcon, FaSolidRobot as AssistantIcon } from "danx-icon";
import { computed } from "vue";

const props = defineProps<{
	message: ThreadMessage;
}>();
const MessageIcon = computed(() => props.message.role === "user" ? UserIcon : AssistantIcon);
</script>
