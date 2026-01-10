<template>
	<div
		class="bg-slate-800 rounded-lg px-3 py-2 border transition-all"
		:class="[
			isExpiringSoon ? 'border-orange-500 bg-slate-750' : 'border-slate-700 hover:border-sky-500 hover:bg-slate-750'
		]"
	>
		<!-- Single Line Layout -->
		<div class="flex flex-col gap-2">
			<!-- Main Row: Resource Type, Scope, Events, and Timestamp -->
			<div class="flex items-center justify-between gap-3">
				<!-- Left Side: Resource Type, Scope, Events -->
				<div class="flex items-center gap-2 flex-1 min-w-0">
					<!-- Resource Type with Subscription ID tooltip -->
					<div class="flex-shrink-0">
						<LabelPillWidget
							:label="`${subscription.resourceType} ${subscription.id.slice(-6)}`"
							color="sky"
							size="sm"
						/>
						<QTooltip class="text-nowrap">{{ subscription.id }}</QTooltip>
					</div>

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
						color="blue"
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
							color="blue"
							size="sm"
						/>
						<QPopupProxy>
							<div class="bg-slate-800 border border-slate-600 rounded-lg p-4 max-w-2xl">
								<div class="text-xs text-slate-400 mb-2 font-semibold">Filter JSON:</div>
								<CodeViewer
									:model-value="filterJsonContent"
									format="json"
									editor-class="w-full bg-slate-900 text-white"
								/>
							</div>
						</QPopupProxy>
					</div>

					<!-- Event Labels with Counts (Combined) -->
					<div class="flex items-center gap-1 flex-shrink-0">
						<!-- Total Events Pill -->
						<div
							class="cursor-pointer hover:opacity-80 transition-opacity"
							@click.stop="toggleEventFilter('__all__')"
						>
							<LabelPillWidget
								:label="`events (${totalEventCount})`"
								:color="selectedEvent === '__all__' ? 'purple' : 'slate'"
								size="xs"
							/>
						</div>

						<!-- Individual Event Type Pills -->
						<div
							v-for="event in sortedEvents"
							:key="event"
							class="cursor-pointer hover:opacity-80 transition-opacity"
							@click.stop="toggleEventFilter(event)"
						>
							<LabelPillWidget
								:label="`${event} (${getEventCount(event)})`"
								:color="selectedEvent === event ? 'purple' : getEventPillColor(event)"
								size="xs"
							/>
						</div>
					</div>
				</div>

				<!-- Right Side: Timestamp Pill -->
				<div v-if="subscription.createdAt && subscription.expiresAt" class="flex-shrink-0">
					<TimestampPill
						:created-at="subscription.createdAt"
						:expires-at="subscription.expiresAt"
						:current-time="now"
					/>
				</div>
			</div>

			<!-- Expanded Event List (when event label is clicked) -->
			<div v-if="selectedEvent && filteredEvents.length > 0" class="border-t border-slate-700 pt-3 mt-1">
				<div class="flex items-center justify-between mb-2">
					<div class="text-sm text-slate-400 font-medium">
						{{ selectedEvent === '__all__' ? 'All' : selectedEvent }} Events ({{ filteredEvents.length }})
					</div>
					<ActionButton
						type="cancel"
						size="xs"
						color="slate"
						tooltip="Close event list"
						@click.stop="selectedEvent = null"
					/>
				</div>
				<!-- Fixed height container with flex layout -->
				<div class="h-[41rem] bg-slate-950 p-4 rounded-lg flex flex-col">
					<!-- Scrollable event list (flex-grow) -->
					<div class="flex-grow overflow-auto space-y-2">
						<EventLogItem
							v-for="(event, index) in paginatedEvents"
							:key="event.timestamp.getTime() + index"
							:event="event"
							:event-color="getEventPillColor(event.eventName)"
							:subscriptions="activeSubscriptions"
						/>
					</div>

					<!-- Pagination at bottom -->
					<div class="mt-3 pt-3 border-t border-slate-800">
						<PaginationNavigator
							v-model="paginationModel"
							:page-sizes="[10, 20, 50]"
							:default-size="10"
						/>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import EventLogItem from "@/components/Developer/EventLogItem.vue";
import PaginationNavigator from "@/components/Shared/Utilities/PaginationNavigator.vue";
import TimestampPill from "@/components/Developer/TimestampPill.vue";
import type { PusherEvent } from "@/types/pusher-debug";
import { QPopupProxy, QTooltip } from "quasar";
import { ActionButton, CodeViewer, LabelPillWidget } from "quasar-ui-danx";
import { computed, onMounted, onUnmounted, ref } from "vue";

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
	eventLog?: PusherEvent[];
	activeSubscriptions?: Map<string, Subscription>;
}>();

// Track selected event for filtering
const selectedEvent = ref<string | null>(null);
const currentPage = ref(1);
const eventsPerPage = 10;

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

// Sort events alphabetically
const sortedEvents = computed(() => {
	return [...props.subscription.events].sort((a, b) => a.localeCompare(b));
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

const filterJsonContent = computed(() => {
	return JSON.stringify(props.subscription.modelIdOrFilter, null, 2);
});

// Get event count for a specific event (defaults to 0 if not found)
function getEventCount(eventName: string): number {
	return props.eventCounts?.[eventName] || 0;
}

// Calculate total event count across all event types
const totalEventCount = computed(() => {
	if (!props.eventCounts) return 0;
	return Object.values(props.eventCounts).reduce((sum, count) => sum + count, 0);
});

// Filter events by subscription ID and selected event name
const filteredEvents = computed(() => {
	if (!selectedEvent.value || !props.eventLog) return [];

	return props.eventLog.filter(event => {
		// Check if this event matches the subscription
		const matchesSubscription = event.matchingSubscriptions?.includes(props.subscription.id);

		// If showing all events, just check subscription match
		if (selectedEvent.value === '__all__') {
			return matchesSubscription;
		}

		// Check if this event matches the selected event name
		const matchesEventName = event.eventName === selectedEvent.value;

		return matchesSubscription && matchesEventName;
	}).reverse(); // Newest first
});

// Pagination model for PaginationNavigator
const paginationModel = computed({
	get: () => ({
		page: currentPage.value,
		perPage: eventsPerPage,
		total: filteredEvents.value.length
	}),
	set: (value) => {
		currentPage.value = value.page;
	}
});

// Paginated events
const totalPages = computed(() => Math.ceil(filteredEvents.value.length / eventsPerPage));

const paginatedEvents = computed(() => {
	const start = (currentPage.value - 1) * eventsPerPage;
	const end = start + eventsPerPage;
	return filteredEvents.value.slice(start, end);
});

function toggleEventFilter(eventName: string) {
	if (selectedEvent.value === eventName) {
		selectedEvent.value = null;
		currentPage.value = 1;
	} else {
		selectedEvent.value = eventName;
		currentPage.value = 1;
	}
}

function getEventPillColor(eventName: string): string {
	const colors: Record<string, string> = {
		created: "emerald",
		updated: "indigo",
		deleted: "orange",
		saved: "violet",
		started: "lime",
		completed: "teal",
		failed: "red",
		cancelled: "rose"
	};

	return colors[eventName.toLowerCase()] || "cyan";
}
</script>
