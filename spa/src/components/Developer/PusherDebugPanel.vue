<template>
	<FullScreenDialog
		:model-value="true"
		:closeable="false"
		content-class="bg-slate-900 p-0 flex flex-col h-full"
		@close="$emit('close')"
	>
		<!-- Header -->
		<PusherDebugHeader
			:connection-state="connectionState"
			:active-subscription-count="activeSubscriptionCount"
			:total-event-count="totalEventCount"
			:keepalive-state="keepaliveState"
			@close="$emit('close')"
		/>

		<!-- Subscription List (Single Page View) -->
		<div class="flex-grow overflow-auto p-6">
			<div v-if="activeSubscriptions.size === 0" class="text-center text-slate-400 py-8">
				No active subscriptions
			</div>
			<div v-else class="space-y-3">
				<SubscriptionListItem
					v-for="[subscriptionId, subscription] in activeSubscriptions"
					:key="subscriptionId"
					:subscription="subscription"
					:subscription-key="subscriptionId"
					:event-counts="subscriptionEventCounts.get(subscriptionId) || {}"
					:event-log="eventLog"
					:active-subscriptions="activeSubscriptions"
				/>
			</div>
		</div>
	</FullScreenDialog>
</template>

<script setup lang="ts">
import PusherDebugHeader from "@/components/Developer/PusherDebugHeader.vue";
import SubscriptionListItem from "@/components/Developer/SubscriptionListItem.vue";
import { usePusher } from "@/helpers/pusher";
import type { PusherEvent } from "@/types/pusher-debug";
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
const keepaliveState = pusher?.keepaliveState || ref({
	lastKeepaliveAt: null,
	nextKeepaliveAt: null,
	keepaliveCount: 0,
	lastKeepaliveSuccess: null,
	lastKeepaliveError: null
});

// Use computed to ensure reactivity with the actual pusher connectionState ref
const connectionState = computed(() => pusher?.connectionState?.value || 'initialized');

// Active subscription count
const activeSubscriptionCount = computed(() => activeSubscriptions.value.size);

// Total event count
const totalEventCount = computed(() => {
	let total = 0;
	eventCounts.value.forEach(count => total += count);
	return total;
});
</script>
