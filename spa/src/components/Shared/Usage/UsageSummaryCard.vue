<template>
	<div v-if="usage && usage.total_cost > 0" :class="cardClasses">
		<div class="flex items-center justify-between">
			<div class="flex items-center gap-3">
				<FaSolidDollarSign :class="`w-4 ${iconColor}`" />
				<div>
					<div class="font-semibold">{{ fCurrency(usage.total_cost) }}</div>
					<div class="text-xs opacity-70">
						<span v-if="usage.total_tokens > 0">{{ fNumber(usage.total_tokens) }} tokens</span>
						<span v-else-if="usage.request_count > 0">{{ fNumber(usage.request_count) }} requests</span>
						<span v-if="usage.count > 1"> • {{ usage.count }} events</span>
					</div>
				</div>
			</div>
			
			<div v-if="showBreakdown" class="text-right text-xs">
				<div v-if="usage.input_cost > 0" class="opacity-70">
					In: {{ fCurrency(usage.input_cost, { maximumFractionDigits: 4 }) }}
				</div>
				<div v-if="usage.output_cost > 0" class="opacity-70">
					Out: {{ fCurrency(usage.output_cost, { maximumFractionDigits: 4 }) }}
				</div>
				<div v-if="usage.run_time_ms > 0" class="opacity-70">
					{{ formatRunTime(usage.run_time_ms) }}
				</div>
			</div>
		</div>

		<!-- Progress bar showing cost distribution -->
		<div v-if="showProgressBar && (usage.input_cost > 0 || usage.output_cost > 0)" class="mt-3">
			<div class="flex h-1.5 bg-slate-700 rounded overflow-hidden">
				<div 
					v-if="inputCostPercent > 0"
					class="bg-blue-400 transition-all duration-300"
					:style="{ width: inputCostPercent + '%' }"
				></div>
				<div 
					v-if="outputCostPercent > 0"
					class="bg-green-400 transition-all duration-300"
					:style="{ width: outputCostPercent + '%' }"
				></div>
			</div>
		</div>

		<!-- Click handler for detailed view -->
		<QBtn
			v-if="showExpandButton"
			flat
			dense
			size="sm"
			class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity"
			@click="$emit('expand')"
		>
			<FaSolidExpand class="w-3" />
		</QBtn>
	</div>
</template>

<script setup lang="ts">
import { computed } from "vue";
import { UsageSummary } from "@/types";
import { fCurrency, fNumber } from "quasar-ui-danx";
import { FaSolidDollarSign, FaSolidExpand } from "danx-icon";

const props = withDefaults(defineProps<{
	usage?: UsageSummary | null;
	variant?: 'default' | 'compact' | 'minimal';
	showBreakdown?: boolean;
	showProgressBar?: boolean;
	showExpandButton?: boolean;
}>(), {
	variant: 'default',
	showBreakdown: true,
	showProgressBar: true,
	showExpandButton: false,
});

defineEmits<{
	expand: [];
}>();

const cardClasses = computed(() => [
	'relative group transition-all duration-200',
	{
		// Default variant
		'bg-slate-800 border border-slate-600 rounded-lg p-4': props.variant === 'default',
		// Compact variant
		'bg-slate-800/50 border border-slate-600/50 rounded p-3': props.variant === 'compact',
		// Minimal variant
		'p-2': props.variant === 'minimal',
	}
]);

const iconColor = computed(() => {
	if (!props.usage) return 'text-slate-400';
	
	if (props.usage.total_cost > 1) return 'text-red-400';
	if (props.usage.total_cost > 0.1) return 'text-yellow-400';
	return 'text-green-400';
});

const inputCostPercent = computed(() => {
	if (!props.usage) return 0;
	const total = props.usage.input_cost + props.usage.output_cost;
	return total > 0 ? (props.usage.input_cost / total) * 100 : 0;
});

const outputCostPercent = computed(() => {
	if (!props.usage) return 0;
	const total = props.usage.input_cost + props.usage.output_cost;
	return total > 0 ? (props.usage.output_cost / total) * 100 : 0;
});

function formatRunTime(ms: number): string {
	if (!ms) return "0ms";
	
	if (ms < 1000) {
		return `${ms}ms`;
	} else if (ms < 60000) {
		return `${(ms / 1000).toFixed(1)}s`;
	} else {
		const minutes = Math.floor(ms / 60000);
		const seconds = ((ms % 60000) / 1000).toFixed(0);
		return `${minutes}m ${seconds}s`;
	}
}
</script>