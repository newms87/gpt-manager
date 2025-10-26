<template>
	<div class="bg-slate-800 p-4 border-b border-slate-700 flex-shrink-0">
		<div class="flex items-center justify-between">
			<div class="flex items-center space-x-4">
				<BugIcon class="w-6 text-sky-400" />
				<h2 class="text-xl font-semibold text-slate-200">
					Pusher Debug Panel
				</h2>
			</div>
			<div class="flex items-center space-x-4 text-sm">
				<div class="flex items-center space-x-2">
					<div :class="isConnected ? 'w-2 h-2 bg-green-500 rounded-full' : 'w-2 h-2 bg-red-500 rounded-full'" />
					<span class="text-slate-300">{{ isConnected ? 'Connected' : 'Disconnected' }}</span>
				</div>
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
		</div>
	</div>
</template>

<script setup lang="ts">
import { FaSolidBug as BugIcon } from "danx-icon";
import { QBtn, QTooltip } from "quasar";
import { LabelPillWidget } from "quasar-ui-danx";

defineProps<{
	isConnected: boolean;
	activeSubscriptionCount: number;
	totalEventCount: number;
}>();

defineEmits<{
	close: [];
}>();
</script>
