<template>
	<div
		class="bg-slate-800 rounded-lg p-4 border border-slate-700 hover:border-sky-500 hover:bg-slate-750 transition-all cursor-pointer"
		@click="$emit('click')"
	>
		<div class="flex items-start justify-between mb-3">
			<div class="flex-1">
				<h3 class="text-lg font-semibold text-sky-400 mb-2">
					{{ subscription.resourceType }}
				</h3>
				<div class="flex items-center space-x-2">
					<span class="text-xs text-slate-500 font-medium">Scope:</span>
					<span v-if="scopeLabel === 'All'" class="text-sm text-sky-400 font-medium">
						{{ scopeLabel }}
					</span>
					<span v-else-if="scopeLabel.startsWith('Model ID:')" class="text-sm text-green-400">
						{{ scopeLabel }}
					</span>
					<span v-else class="text-sm text-purple-400">
						{{ scopeLabel }}
					</span>
				</div>
			</div>
		</div>

		<div class="flex flex-wrap gap-1 mb-2">
			<LabelPillWidget
				v-for="event in subscription.events"
				:key="event"
				:label="event"
				color="slate"
				size="xs"
			/>
		</div>

		<!-- Event Counts -->
		<div v-if="hasEventCounts" class="mt-3 pt-3 border-t border-slate-700">
			<div class="text-xs text-slate-400 font-medium mb-2">Event Counts:</div>
			<div class="flex flex-wrap gap-2">
				<div
					v-for="(count, eventName) in eventCounts"
					:key="eventName"
					class="flex items-center space-x-2 bg-slate-700 rounded px-2 py-1"
				>
					<span class="text-xs font-medium" :class="getEventColor(eventName as string)">
						{{ eventName }}
					</span>
					<span class="text-xs text-slate-300 font-bold">
						{{ count }}
					</span>
				</div>
			</div>
		</div>

		<div class="text-xs text-slate-500 font-mono mt-2 truncate" :title="subscriptionKey">
			{{ subscriptionKey }}
		</div>
	</div>
</template>

<script setup lang="ts">
import { LabelPillWidget } from "quasar-ui-danx";
import { computed } from "vue";

interface Subscription {
	resourceType: string;
	modelIdOrFilter: boolean | number | string;
	events: string[];
}

const props = defineProps<{
	subscription: Subscription;
	subscriptionKey: string;
	eventCounts?: Record<string, number>;
}>();

defineEmits<{
	click: [];
}>();

const scopeLabel = computed(() => {
	if (props.subscription.modelIdOrFilter === true) {
		return "All";
	} else if (typeof props.subscription.modelIdOrFilter === "number") {
		return `Model ID: ${props.subscription.modelIdOrFilter}`;
	} else {
		const filterHash = props.subscriptionKey.split(":filter:")[1];
		return `Filter: ${filterHash}`;
	}
});

const hasEventCounts = computed(() => {
	return props.eventCounts && Object.keys(props.eventCounts).length > 0;
});

function getEventColor(eventName: string): string {
	const colors: Record<string, string> = {
		created: "text-green-400",
		updated: "text-blue-400",
		deleted: "text-red-400",
		saved: "text-purple-400",
		started: "text-sky-400",
		completed: "text-green-400",
		failed: "text-red-400",
		cancelled: "text-yellow-400"
	};

	return colors[eventName.toLowerCase()] || "text-slate-300";
}
</script>
