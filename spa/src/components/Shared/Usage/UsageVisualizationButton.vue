<template>
	<QBtn class="text-xs">
		<div class="flex items-center gap-2">
			<div class="text-xs font-medium">
				{{ fCurrency(usage.total_cost) }}
			</div>
			<div v-if="usage.total_tokens > 0" class="text-xs opacity-70">
				{{ fNumber(usage.total_tokens) }} tokens
			</div>
			<div v-if="usage.request_count > 0" class="text-xs opacity-70">
				{{ fNumber(usage.request_count) }} requests
			</div>
		</div>
		<QMenu>
			<div class="p-6 min-w-96">
				<div class="text-lg font-semibold mb-4 flex items-center gap-2">
					<FaSolidChartLine class="w-4 text-blue-400" />
					Usage Summary
					<span v-if="usage.count" class="text-sm opacity-70">({{ usage.count }} events)</span>
				</div>
				
				<!-- Cost Breakdown -->
				<div class="grid grid-cols-3 gap-4 mb-6">
					<LabelValueFormat 
						label="Total Cost" 
						:value="fCurrency(usage.total_cost, { minimumFractionDigits: 4, maximumFractionDigits: 4 })"
						class="col-span-3 text-lg font-semibold"
					/>
					<LabelValueFormat
						label="Input Cost" 
						:value="fCurrency(usage.input_cost, { minimumFractionDigits: 4, maximumFractionDigits: 4 })"
					/>
					<LabelValueFormat
						label="Output Cost" 
						:value="fCurrency(usage.output_cost, { minimumFractionDigits: 4, maximumFractionDigits: 4 })"
					/>
					<LabelValueFormat
						label="Run Time" 
						:value="formatRunTime(usage.run_time_ms)"
					/>
				</div>

				<!-- Token Usage (if applicable) -->
				<div v-if="usage.total_tokens > 0" class="mb-6">
					<div class="text-sm font-medium mb-2 text-blue-400">Token Usage</div>
					<div class="grid grid-cols-3 gap-4">
						<LabelValueFormat label="Total Tokens" :value="fNumber(usage.total_tokens)" />
						<LabelValueFormat label="Input Tokens" :value="fNumber(usage.input_tokens)" />
						<LabelValueFormat label="Output Tokens" :value="fNumber(usage.output_tokens)" />
					</div>
				</div>

				<!-- API Usage (if applicable) -->
				<div v-if="usage.request_count > 0" class="mb-6">
					<div class="text-sm font-medium mb-2 text-green-400">API Requests</div>
					<div class="grid grid-cols-2 gap-4">
						<LabelValueFormat label="Requests" :value="fNumber(usage.request_count)" />
						<LabelValueFormat 
							v-if="usage.data_volume > 0"
							label="Data Volume" 
							:value="formatDataVolume(usage.data_volume)" 
						/>
					</div>
				</div>

				<!-- Visual representation -->
				<div v-if="usage.input_cost > 0 || usage.output_cost > 0" class="mb-4">
					<div class="text-sm font-medium mb-2">Cost Distribution</div>
					<div class="flex h-3 bg-slate-700 rounded overflow-hidden">
						<div 
							v-if="inputCostPercent > 0"
							class="bg-blue-500 transition-all duration-300"
							:style="{ width: inputCostPercent + '%' }"
							:title="`Input: ${fCurrency(usage.input_cost)} (${inputCostPercent.toFixed(1)}%)`"
						></div>
						<div 
							v-if="outputCostPercent > 0"
							class="bg-green-500 transition-all duration-300"
							:style="{ width: outputCostPercent + '%' }"
							:title="`Output: ${fCurrency(usage.output_cost)} (${outputCostPercent.toFixed(1)}%)`"
						></div>
					</div>
					<div class="flex justify-between text-xs mt-1 opacity-70">
						<span>Input ({{ inputCostPercent.toFixed(1) }}%)</span>
						<span>Output ({{ outputCostPercent.toFixed(1) }}%)</span>
					</div>
				</div>

				<!-- Efficiency Metrics -->
				<div v-if="efficiency" class="pt-4 border-t border-slate-600">
					<div class="text-xs opacity-70">
						<div v-if="efficiency.costPerToken">{{ fCurrency(efficiency.costPerToken, { minimumFractionDigits: 6 }) }} per token</div>
						<div v-if="efficiency.tokensPerSecond">{{ fNumber(efficiency.tokensPerSecond, { maximumFractionDigits: 1 }) }} tokens/sec</div>
						<div v-if="efficiency.costPerRequest">{{ fCurrency(efficiency.costPerRequest) }} per request</div>
					</div>
				</div>
			</div>
		</QMenu>
	</QBtn>
</template>

<script setup lang="ts">
import { computed } from "vue";
import { UsageSummary } from "@/types";
import { fCurrency, fNumber, LabelValueFormat } from "quasar-ui-danx";
import { FaSolidChartLine } from "danx-icon";

const props = defineProps<{
	usage: UsageSummary;
}>();

const inputCostPercent = computed(() => {
	const total = props.usage.input_cost + props.usage.output_cost;
	return total > 0 ? (props.usage.input_cost / total) * 100 : 0;
});

const outputCostPercent = computed(() => {
	const total = props.usage.input_cost + props.usage.output_cost;
	return total > 0 ? (props.usage.output_cost / total) * 100 : 0;
});

const efficiency = computed(() => {
	if (!props.usage) return null;
	
	const result: any = {};
	
	if (props.usage.total_tokens > 0) {
		result.costPerToken = props.usage.total_cost / props.usage.total_tokens;
		
		if (props.usage.run_time_ms > 0) {
			result.tokensPerSecond = (props.usage.total_tokens / props.usage.run_time_ms) * 1000;
		}
	}
	
	if (props.usage.request_count > 0) {
		result.costPerRequest = props.usage.total_cost / props.usage.request_count;
	}
	
	return Object.keys(result).length > 0 ? result : null;
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

function formatDataVolume(bytes: number): string {
	if (!bytes) return "0 B";
	
	const units = ['B', 'KB', 'MB', 'GB'];
	let size = bytes;
	let unitIndex = 0;
	
	while (size >= 1024 && unitIndex < units.length - 1) {
		size /= 1024;
		unitIndex++;
	}
	
	return `${size.toFixed(unitIndex === 0 ? 0 : 1)} ${units[unitIndex]}`;
}
</script>

<style lang="scss" scoped>
.usage-visualization-btn {
	&:hover {
		.efficiency-bar {
			opacity: 1;
		}
	}
}
</style>