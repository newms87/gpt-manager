<template>
	<QChip
		clickable
		dense
		class="cursor-pointer hover:opacity-80 px-2 py-1 text-xs"
		:class="[colorConfig.bg, colorConfig.text]"
	>
		<QPopupProxy
			anchor="bottom left"
			self="top left"
			class="bg-slate-800 rounded-lg shadow-lg border border-slate-700"
			@show="() => console.log('[EmbeddedObjectLink] Popover shown')"
			@hide="() => console.log('[EmbeddedObjectLink] Popover hidden')"
		>
			<div class="p-4 max-w-md">
				<h4 class="text-sm font-semibold text-slate-200 mb-3">
					{{ object.className }} Details
				</h4>

				<div class="space-y-2">
					<div
						v-for="(value, key) in object.attributes"
						:key="key"
						class="border-b border-slate-700 pb-2"
					>
						<div class="text-xs font-medium text-slate-400 capitalize">
							{{ key }}
						</div>
						<div class="text-sm text-slate-200 whitespace-pre-wrap">
							{{ value }}
						</div>
					</div>
				</div>
			</div>
		</QPopupProxy>

		<span>{{ displayText }}</span>
	</QChip>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { QChip, QPopupProxy } from 'quasar';
import { getHashedColor } from './logHelpers';
import type { EmbeddedObject } from './useLogParser';

const props = defineProps<{
	object: EmbeddedObject;
}>();

const displayText = computed(() => {
	const parts = [props.object.className];

	if (props.object.id) {
		parts.push(props.object.id);
	}

	if (props.object.attributes?.status) {
		parts.push(props.object.attributes.status);
	}

	if (props.object.name) {
		parts.push(props.object.name);
	}

	return parts.join(' ');
});

const colorConfig = computed(() => getHashedColor(props.object.className));
</script>
