<template>
	<div
		class="bg-slate-800 rounded-lg px-3 py-2 border transition-all cursor-pointer"
		:class="[
			isExpiringSoon ? 'border-orange-500 bg-slate-750' : 'border-slate-700 hover:border-sky-500 hover:bg-slate-750'
		]"
		@click="$emit('click')"
	>
		<!-- Single Line Layout -->
		<div class="flex flex-col gap-2">
			<!-- Main Row: Resource Type, Scope, Events, Expiration, ID -->
			<div class="flex items-center justify-between gap-3">
				<!-- Left Side: Resource Type, Scope, Events -->
				<div class="flex items-center gap-2 flex-1 min-w-0">
				<!-- Resource Type -->
				<LabelPillWidget
					:label="subscription.resourceType"
					color="sky"
					size="sm"
					class="flex-shrink-0"
				/>

				<!-- Scope -->
				<LabelPillWidget
					v-if="scopeLabel === 'All'"
					label="ALL"
					color="blue"
					size="sm"
					class="flex-shrink-0"
				/>
				<LabelPillWidget
					v-else-if="scopeType === 'model'"
					:label="`ID: ${scopeValue}`"
					color="green"
					size="sm"
					class="flex-shrink-0"
				/>
				<div
					v-else
					class="flex-shrink-0 cursor-pointer hover:opacity-80 transition-opacity"
					@click.stop
				>
					<LabelPillWidget
						:label="`Filter: ${scopeValue}`"
						color="amber"
						size="sm"
					/>
					<QPopupProxy>
						<div class="bg-slate-800 border border-slate-600 rounded-lg p-4 max-w-2xl">
							<div class="text-xs text-slate-400 mb-2 font-semibold">Filter JSON:</div>
							<MarkdownEditor
								:model-value="filterJsonContent"
								:readonly="true"
								format="json"
								editor-class="w-full bg-slate-900 text-white"
							/>
						</div>
					</QPopupProxy>
				</div>

				<!-- Events -->
				<div class="flex items-center gap-1 flex-shrink-0">
					<LabelPillWidget
						v-for="event in subscription.events"
						:key="event"
						:label="event"
						:color="getEventPillColor(event)"
						size="xs"
					/>
				</div>

				<!-- Event Counts -->
				<div v-if="hasEventCounts" class="flex items-center gap-1 flex-shrink-0">
					<LabelPillWidget
						v-for="(count, eventName) in eventCounts"
						:key="eventName"
						:label="`${eventName}: ${count}`"
						color="slate"
						size="xs"
					/>
				</div>
			</div>

			<!-- Right Side: Expiration, ID -->
			<div class="flex items-center gap-2 flex-shrink-0">
				<!-- Expiration -->
				<div v-if="subscription.expiresAt" class="flex items-center gap-1.5">
					<LabelPillWidget
						:label="formatTimeRemaining(timeRemaining)"
						:color="isExpiringSoon ? 'orange' : 'slate'"
						size="sm"
					/>
					<span
						class="text-xs font-medium"
						:class="isExpiringSoon ? 'text-orange-400' : 'text-slate-400'"
					>
						{{ formatExpiration(subscription.expiresAt) }}
					</span>
				</div>

					<!-- Full ID -->
					<span class="text-xs text-slate-500 font-mono select-all" :title="subscription.id">
						{{ subscription.id }}
					</span>
				</div>
			</div>

			<!-- Metadata Row: Cache Key, Creation Time -->
			<div class="flex items-center gap-3 text-xs text-slate-500">
				<div v-if="subscription.cacheKey" class="flex items-center gap-1">
					<span class="font-semibold">Cache:</span>
					<span class="font-mono select-all">{{ subscription.cacheKey }}</span>
				</div>
				<div v-if="subscription.createdAt" class="flex items-center gap-1">
					<span class="font-semibold">Created:</span>
					<span class="font-mono">{{ formatCreatedAt(subscription.createdAt) }}</span>
					<span class="text-slate-600">({{ formatTimeSinceCreation(subscription.createdAt) }} ago)</span>
				</div>
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import MarkdownEditor from "@/components/MarkdownEditor/MarkdownEditor.vue";
import { LabelPillWidget } from "quasar-ui-danx";
import { QPopupProxy } from "quasar";
import { computed, ref, onMounted, onUnmounted } from "vue";

interface Subscription {
	id: string;
	resourceType: string;
	modelIdOrFilter: boolean | number | string;
	events: string[];
	expiresAt?: string;
	cacheKey?: string;
	createdAt?: Date;
}

const props = defineProps<{
	subscription: Subscription;
	subscriptionKey: string;
	eventCounts?: Record<string, number>;
}>();

defineEmits<{
	click: [];
}>();

// Track current time for reactive time remaining
const now = ref(Date.now());
let intervalId: NodeJS.Timeout | null = null;

onMounted(() => {
	// Update time every second
	intervalId = setInterval(() => {
		now.value = Date.now();
	}, 1000);
});

onUnmounted(() => {
	if (intervalId) {
		clearInterval(intervalId);
	}
});

const scopeLabel = computed(() => {
	if (props.subscription.modelIdOrFilter === true) {
		return "All";
	} else if (typeof props.subscription.modelIdOrFilter === "number") {
		return `Model ID: ${props.subscription.modelIdOrFilter}`;
	} else if (typeof props.subscription.modelIdOrFilter === "string") {
		return `Model ID: ${props.subscription.modelIdOrFilter}`;
	} else {
		const filterHash = props.subscriptionKey.split(":filter:")[1] || props.subscription.id.substring(0, 8);
		return `Filter: ${filterHash}`;
	}
});

const scopeType = computed(() => {
	if (props.subscription.modelIdOrFilter === true) {
		return "all";
	} else if (typeof props.subscription.modelIdOrFilter === "number" || typeof props.subscription.modelIdOrFilter === "string") {
		return "model";
	} else {
		return "filter";
	}
});

const scopeValue = computed(() => {
	if (scopeType.value === "model") {
		return String(props.subscription.modelIdOrFilter);
	} else if (scopeType.value === "filter") {
		// For filter objects, show first 8 chars of subscription ID as identifier
		return props.subscription.id.substring(0, 8);
	}
	return "";
});

const timeRemaining = computed(() => {
	if (!props.subscription.expiresAt) return 0;
	const expiresAt = new Date(props.subscription.expiresAt);
	return expiresAt.getTime() - now.value;
});

const isExpiringSoon = computed(() => {
	return timeRemaining.value > 0 && timeRemaining.value < 60000; // Less than 1 minute
});

const hasEventCounts = computed(() => {
	return props.eventCounts && Object.keys(props.eventCounts).length > 0;
});

const filterJsonContent = computed(() => {
	return JSON.stringify(props.subscription.modelIdOrFilter, null, 2);
});

function formatExpiration(isoString: string): string {
	if (!isoString) return "Unknown";
	const date = new Date(isoString);
	return date.toLocaleTimeString();
}

function formatTimeRemaining(ms: number): string {
	if (ms < 0) return "EXPIRED";
	const seconds = Math.floor(ms / 1000);
	if (seconds < 60) return `${seconds}s`;
	const minutes = Math.floor(seconds / 60);
	const remainingSeconds = seconds % 60;
	return `${minutes}m ${remainingSeconds}s`;
}

function getEventPillColor(eventName: string): string {
	const colors: Record<string, string> = {
		created: "green",
		updated: "blue",
		deleted: "red",
		saved: "purple",
		started: "sky",
		completed: "green",
		failed: "red",
		cancelled: "yellow"
	};

	return colors[eventName.toLowerCase()] || "slate";
}

function formatCreatedAt(date: Date): string {
	const hours = date.getHours().toString().padStart(2, '0');
	const minutes = date.getMinutes().toString().padStart(2, '0');
	const seconds = date.getSeconds().toString().padStart(2, '0');
	return `${hours}:${minutes}:${seconds}`;
}

function formatTimeSinceCreation(createdAt: Date): string {
	const elapsed = now.value - createdAt.getTime();
	const seconds = Math.floor(elapsed / 1000);

	if (seconds < 60) return `${seconds}s`;

	const minutes = Math.floor(seconds / 60);
	if (minutes < 60) {
		const remainingSeconds = seconds % 60;
		return `${minutes}m ${remainingSeconds}s`;
	}

	const hours = Math.floor(minutes / 60);
	const remainingMinutes = minutes % 60;
	return `${hours}h ${remainingMinutes}m`;
}
</script>
