<template>
    <FullScreenDialog
        :model-value="true"
        :closeable="false"
        content-class="bg-slate-900 p-0 flex flex-col h-full"
        @close="$emit('close')"
    >
        <!-- Header -->
        <PusherDebugHeader
            :is-connected="isConnected"
            :active-subscription-count="activeSubscriptionCount"
            :total-event-count="totalEventCount"
            @close="$emit('close')"
        />

        <!-- Tabs -->
        <div class="flex-shrink-0 bg-slate-800 border-b border-slate-700">
            <QTabs
                v-model="activeTab"
                dense
                class="tab-buttons border-sky-900 bg-sky-950 text-sky-200"
                active-color="sky-200"
                indicator-color="sky-500"
            >
                <QTab name="subscriptions" label="Active Subscriptions" class="font-medium" />
                <QTab name="events" label="Event Log" class="font-medium" />
                <QTab name="statistics" label="Statistics" class="font-medium" />
            </QTabs>
        </div>

        <!-- Tab Panels -->
        <div class="flex-grow overflow-hidden">
            <!-- Active Subscriptions Tab -->
            <div v-if="activeTab === 'subscriptions'" class="h-full overflow-auto p-6">
                <div class="space-y-3">
                    <SubscriptionListItem
                        v-for="[key, subscription] in activeSubscriptions"
                        :key="key"
                        :subscription="subscription"
                        :subscription-key="key"
                        :event-counts="subscriptionEventCounts.get(key) || {}"
                        @click="onSubscriptionClick(key, subscription)"
                    />
                </div>
            </div>

            <!-- Event Log Tab -->
            <div v-if="activeTab === 'events'" class="h-full flex flex-col">
                <!-- Filters -->
                <PusherDebugFilters
                    v-model:search-text="eventSearchText"
                    v-model:resource-type-filter="eventResourceTypeFilter"
                    v-model:event-name-filter="eventNameFilter"
                    :resource-type-options="resourceTypeOptions"
                    :event-name-options="eventNameOptions"
                    :filtered-count="filteredEvents.length"
                    :total-count="eventLog.length"
                    :is-filtered-by-subscription="isFilteredBySubscription"
                    @clear-logs="onClearLogs"
                    @clear-filters="clearFilters"
                />

                <!-- Event List -->
                <div class="flex-grow overflow-auto p-6">
                    <div class="space-y-2">
                        <EventLogItem
                            v-for="event in filteredEvents"
                            :key="event.timestamp.getTime()"
                            :event="event"
                        />
                    </div>
                </div>
            </div>

            <!-- Statistics Tab -->
            <div v-if="activeTab === 'statistics'" class="h-full overflow-auto p-6">
                <div class="space-y-3">
                    <EventStatItem
                        v-for="stat in statisticsRows"
                        :key="stat.key"
                        :resource-type="stat.resourceType"
                        :event-name="stat.eventName"
                        :count="stat.count"
                        :max-count="maxEventCount"
                    />
                </div>
            </div>
        </div>
    </FullScreenDialog>
</template>

<script setup lang="ts">
import EventLogItem from "@/components/Developer/EventLogItem.vue";
import EventStatItem from "@/components/Developer/EventStatItem.vue";
import PusherDebugFilters from "@/components/Developer/PusherDebugFilters.vue";
import PusherDebugHeader from "@/components/Developer/PusherDebugHeader.vue";
import SubscriptionListItem from "@/components/Developer/SubscriptionListItem.vue";
import { usePusher } from "@/helpers/pusher";
import type { PusherEvent } from "@/types/pusher-debug";
import { QTab, QTabs } from "quasar";
import { FullScreenDialog } from "quasar-ui-danx";
import { computed, ref } from "vue";

defineEmits<{
    close: [];
}>();

const pusher = usePusher();

// Get reactive data from pusher
const activeSubscriptions = pusher?.activeSubscriptions || ref(new Map());
const eventLog = pusher?.eventLog || ref<PusherEvent[]>([]);
const eventCounts = pusher?.eventCounts || ref(new Map<string, number>());
const subscriptionEventCounts = pusher?.subscriptionEventCounts || ref(new Map<string, Record<string, number>>());

// Tab state
const activeTab = ref<"subscriptions" | "events" | "statistics">("subscriptions");

// Event log filters
const eventSearchText = ref("");
const eventResourceTypeFilter = ref<string | null>(null);
const eventNameFilter = ref<string | null>(null);
const isFilteredBySubscription = ref(false);

// Connection status
const isConnected = computed(() => pusher?.pusher?.connection?.state === "connected");

// Active subscription count
const activeSubscriptionCount = computed(() => activeSubscriptions.value.size);

// Total event count
const totalEventCount = computed(() => {
    let total = 0;
    eventCounts.value.forEach(count => total += count);
    return total;
});

// Resource type options for filter
const resourceTypeOptions = computed(() => {
    const types = new Set<string>();
    eventLog.value.forEach(event => types.add(event.resourceType));
    return Array.from(types).sort();
});

// Event name options for filter
const eventNameOptions = computed(() => {
    const names = new Set<string>();
    eventLog.value.forEach(event => names.add(event.eventName));
    return Array.from(names).sort();
});

// Filtered events (reverse order - newest first)
const filteredEvents = computed(() => {
    let events = [...eventLog.value].reverse();

    // Apply resource type filter
    if (eventResourceTypeFilter.value) {
        events = events.filter(e => e.resourceType === eventResourceTypeFilter.value);
    }

    // Apply event name filter
    if (eventNameFilter.value) {
        events = events.filter(e => e.eventName === eventNameFilter.value);
    }

    // Apply text search
    if (eventSearchText.value.trim()) {
        const searchLower = eventSearchText.value.toLowerCase();
        events = events.filter(e =>
            JSON.stringify(e.payload).toLowerCase().includes(searchLower)
        );
    }

    return events;
});

// Max event count for progress bars
const maxEventCount = computed(() => {
    let max = 0;
    eventCounts.value.forEach(count => {
        if (count > max) max = count;
    });
    return max || 1; // Prevent division by zero
});

// Statistics table rows
const statisticsRows = computed(() => {
    const rows: any[] = [];
    eventCounts.value.forEach((count, key) => {
        const [resourceType, eventName] = key.split(":");
        rows.push({
            key,
            resourceType,
            eventName,
            count
        });
    });
    // Sort by count descending
    return rows.sort((a, b) => b.count - a.count);
});

// Handle subscription click - switch to events tab and filter
function onSubscriptionClick(subscriptionKey: string, subscription: any) {
    // Switch to events tab
    activeTab.value = "events";

    // Set filters based on subscription
    eventResourceTypeFilter.value = subscription.resourceType;

    // If it's a model-specific subscription, we could filter by model ID
    // For now, just filter by resource type
    isFilteredBySubscription.value = true;
}

// Clear filters
function clearFilters() {
    eventSearchText.value = "";
    eventResourceTypeFilter.value = null;
    eventNameFilter.value = null;
    isFilteredBySubscription.value = false;
}

// Clear event logs
function onClearLogs() {
    if (pusher?.clearEventLog) {
        pusher.clearEventLog();
    }
}
</script>
