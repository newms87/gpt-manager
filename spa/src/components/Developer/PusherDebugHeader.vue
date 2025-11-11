<template>
	<div class="bg-slate-800 p-4 border-b border-slate-700 flex-shrink-0">
		<!-- Main Header -->
		<div class="flex items-center justify-between mb-3">
			<div class="flex items-center space-x-4">
				<BugIcon class="w-6 text-sky-400" />
				<h2 class="text-xl font-semibold text-slate-200">
					Pusher Debug Panel
				</h2>
			</div>
			<QBtn
				round
				flat
				icon="close"
				size="sm"
				class="text-slate-400 hover:text-slate-200 hover:bg-slate-700 transition-all"
				@click="$emit('close')"
			>
				<QTooltip>Close Panel</QTooltip>
			</QBtn>
		</div>

		<!-- Stats Row -->
		<div class="grid grid-cols-2 gap-4">
			<!-- Connection & Subscription Stats -->
			<div class="bg-slate-750 rounded-lg p-3 space-y-2">
				<div class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Connection Status</div>
				<div class="flex items-center gap-2">
					<div
						class="w-2 h-2 rounded-full"
						:class="[
							connectionStatusColor,
							connectionState === 'connecting' ? 'animate-pulse' : ''
						]"
					/>
					<span class="text-slate-300 text-sm">{{ connectionStateLabel }}</span>
				</div>
				<div class="flex items-center space-x-2">
					<LabelPillWidget
						:label="`${activeSubscriptionCount} Subscriptions`"
						color="sky"
						size="xs"
					/>
					<LabelPillWidget
						:label="`${totalEventCount} Total Events`"
						color="purple"
						size="xs"
					/>
				</div>
			</div>

			<!-- System Info -->
			<div class="bg-slate-750 rounded-lg p-3 space-y-2">
				<div class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">System Info</div>
				<div class="text-xs text-slate-300 space-y-1">
					<div>
						<span class="text-slate-500">User ID:</span>
						<span class="ml-2 font-mono">{{ userId || 'N/A' }}</span>
					</div>
					<div>
						<span class="text-slate-500">Team ID:</span>
						<span class="ml-2 font-mono">{{ teamId || 'N/A' }}</span>
					</div>
				</div>
			</div>
		</div>

		<!-- Keepalive Status Row -->
		<div v-if="activeSubscriptionCount > 0" class="mt-3 bg-slate-750 rounded-lg p-3">
			<div class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Keepalive Status</div>
			<div class="grid grid-cols-4 gap-4 text-xs">
				<div>
					<div class="text-slate-500 mb-1">Next Refresh</div>
					<div class="text-slate-300 font-mono">
						{{ nextKeepaliveCountdown }}
					</div>
				</div>
				<div>
					<div class="text-slate-500 mb-1">Last Refresh</div>
					<div class="text-slate-300 font-mono">
						{{ lastKeepaliveTime }}
					</div>
				</div>
				<div>
					<div class="text-slate-500 mb-1">Total Cycles</div>
					<div class="text-slate-300 font-mono">
						{{ keepaliveCount }}
					</div>
				</div>
				<div>
					<div class="text-slate-500 mb-1">Status</div>
					<div class="flex items-center space-x-1">
						<div
							v-if="lastKeepaliveSuccess === true"
							class="w-2 h-2 bg-green-500 rounded-full"
						/>
						<div
							v-else-if="lastKeepaliveSuccess === false"
							class="w-2 h-2 bg-red-500 rounded-full"
						/>
						<div
							v-else
							class="w-2 h-2 bg-slate-500 rounded-full"
						/>
						<span class="text-slate-300">
							{{ keepaliveStatusText }}
						</span>
					</div>
					<div v-if="lastKeepaliveError" class="text-red-400 text-xs mt-1" :title="lastKeepaliveError">
						{{ lastKeepaliveError.substring(0, 30) }}...
					</div>
				</div>
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import { authTeam, authUser } from "@/helpers";
import type { KeepaliveState } from "@/types/pusher-debug";
import { FaSolidBug as BugIcon } from "danx-icon";
import { QBtn, QTooltip } from "quasar";
import { LabelPillWidget } from "quasar-ui-danx";
import { computed, onMounted, onUnmounted, ref } from "vue";

const props = defineProps<{
	connectionState: string;
	activeSubscriptionCount: number;
	totalEventCount: number;
	keepaliveState: KeepaliveState;
}>();

defineEmits<{
	close: [];
}>();

// Get user and team info
const userId = computed(() => authUser.value?.id);
const teamId = computed(() => authTeam.value?.id);

// Connection status color based on state
const connectionStatusColor = computed(() => {
	switch (props.connectionState) {
		case 'connected':
			return 'bg-green-500';
		case 'connecting':
			return 'bg-yellow-400';
		case 'initialized':
			return 'bg-blue-500';
		case 'disconnected':
			return 'bg-red-500';
		case 'unavailable':
			return 'bg-orange-500';
		case 'failed':
			return 'bg-red-600';
		default:
			return 'bg-slate-500';
	}
});

// Connection state label (user-friendly text)
const connectionStateLabel = computed(() => {
	switch (props.connectionState) {
		case 'initialized':
			return 'Initializing...';
		case 'connecting':
			return 'Connecting...';
		case 'connected':
			return 'Connected';
		case 'disconnected':
			return 'Disconnected';
		case 'unavailable':
			return 'Unavailable';
		case 'failed':
			return 'Connection Failed';
		default:
			return props.connectionState;
	}
});

// Reactive current time for countdown
const now = ref(Date.now());
let intervalId: NodeJS.Timeout | null = null;

onMounted(() => {
	// Update time every second for countdown
	intervalId = setInterval(() => {
		now.value = Date.now();
	}, 1000);
});

onUnmounted(() => {
	if (intervalId) {
		clearInterval(intervalId);
	}
});

// Keepalive computed values
const nextKeepaliveCountdown = computed(() => {
	if (!props.keepaliveState.nextKeepaliveAt) return 'N/A';
	const timeRemaining = props.keepaliveState.nextKeepaliveAt.getTime() - now.value;
	if (timeRemaining < 0) return 'Overdue';
	const seconds = Math.floor(timeRemaining / 1000);
	return `${seconds}s`;
});

const lastKeepaliveTime = computed(() => {
	if (!props.keepaliveState.lastKeepaliveAt) return 'Never';
	const timestamp = props.keepaliveState.lastKeepaliveAt;
	const hours = timestamp.getHours().toString().padStart(2, '0');
	const minutes = timestamp.getMinutes().toString().padStart(2, '0');
	const seconds = timestamp.getSeconds().toString().padStart(2, '0');
	return `${hours}:${minutes}:${seconds}`;
});

const keepaliveCount = computed(() => props.keepaliveState.keepaliveCount);
const lastKeepaliveSuccess = computed(() => props.keepaliveState.lastKeepaliveSuccess);
const lastKeepaliveError = computed(() => props.keepaliveState.lastKeepaliveError);

const keepaliveStatusText = computed(() => {
	if (props.keepaliveState.lastKeepaliveSuccess === null) return 'Waiting';
	return props.keepaliveState.lastKeepaliveSuccess ? 'Success' : 'Failed';
});
</script>
