<template>
	<div
		v-if="editEnabled"
		class="flex items-center gap-2 px-3 py-3 border-t border-slate-600 cursor-pointer hover:bg-slate-700/50 transition-colors nodrag nopan"
		@click="emit('add-property')"
	>
		<PlusIcon class="w-3 h-3 text-green-500" />
		<span class="text-xs text-green-500">New Property</span>
	</div>
</template>

<script setup lang="ts">
import { FaSolidPlus as PlusIcon } from "danx-icon";

defineProps<{
	editEnabled: boolean;
}>();

const emit = defineEmits<{
	"add-property": [];
}>();
</script>
