<template>
	<div class="bg-slate-800 rounded-lg border border-slate-700 hover:border-slate-600 transition-colors">
		<div class="p-3 flex items-center justify-between cursor-pointer" @click="toggleExpanded">
			<div class="flex items-center space-x-4 flex-1 overflow-hidden">
				<span class="text-slate-300 font-mono text-xs flex-shrink-0">
					{{ formattedTime }}
				</span>
				<LabelPillWidget
					:label="event.resourceType"
					color="sky"
					size="xs"
				/>
				<LabelPillWidget
					:label="event.eventName"
					color="green"
					size="xs"
				/>
				<span v-if="event.modelId" class="text-purple-400 text-xs">
					ID: {{ event.modelId }}
				</span>
			</div>
			<div class="flex items-center space-x-2 flex-shrink-0">
				<ActionButton
					:type="isExpanded ? 'collapse' : 'view'"
					size="xs"
					color="sky"
					@click.stop="toggleExpanded"
				/>
			</div>
		</div>

		<div v-if="isExpanded" class="border-t border-slate-700 bg-slate-950 p-4">
			<div class="text-sm text-slate-400 mb-2">Payload:</div>
			<pre class="text-xs text-slate-300 bg-slate-900 p-3 rounded overflow-auto max-h-96">{{ formattedPayload }}</pre>
		</div>
	</div>
</template>

<script setup lang="ts">
import type { PusherEvent } from "@/types/pusher-debug";
import { ActionButton, LabelPillWidget } from "quasar-ui-danx";
import { computed, ref } from "vue";

const props = defineProps<{
	event: PusherEvent;
}>();

const isExpanded = ref(false);

const formattedTime = computed(() => {
	const timestamp = props.event.timestamp;
	const hours = timestamp.getHours().toString().padStart(2, "0");
	const minutes = timestamp.getMinutes().toString().padStart(2, "0");
	const seconds = timestamp.getSeconds().toString().padStart(2, "0");
	const milliseconds = timestamp.getMilliseconds().toString().padStart(3, "0");
	return `${hours}:${minutes}:${seconds}.${milliseconds}`;
});

const formattedPayload = computed(() => {
	return JSON.stringify(props.event.payload, null, 2);
});

function toggleExpanded() {
	isExpanded.value = !isExpanded.value;
}
</script>
