<template>
	<QChip
		clickable
		dense
		class="cursor-pointer hover:opacity-80 px-2 py-1 text-xs transition-opacity"
		:class="[colorConfig.bg, colorConfig.text]"
		@click="$emit('filter', entity.content)"
	>
		<span>[{{ entity.content }}]</span>
	</QChip>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { QChip } from 'quasar';
import { getHashedColor } from './logHelpers';
import type { SquareBracketEntity } from './useLogParser';

const props = defineProps<{
	entity: SquareBracketEntity;
}>();

defineEmits<{
	filter: [content: string];
}>();

const colorConfig = computed(() => getHashedColor(props.entity.content));
</script>
