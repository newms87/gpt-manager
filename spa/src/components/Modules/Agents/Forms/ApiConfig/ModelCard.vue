<template>
	<div 
		:class="[
			'p-4 border-2 rounded-lg transition-all',
			isSelected 
				? 'border-blue-500 bg-blue-500/10 shadow-lg' 
				: 'border-slate-600 bg-slate-700/30',
			clickable && !isSelected && 'cursor-pointer hover:border-slate-500 hover:bg-slate-700/50'
		]"
		@click="$emit('select')"
	>
		<!-- Header Section -->
		<div class="flex items-start justify-between mb-3">
			<div class="flex items-center gap-3">
				<FaSolidBrain 
					:class="isSelected ? 'text-blue-400' : 'text-slate-400'" 
					class="w-6 h-6 flex-shrink-0" 
				/>
				<div>
					<div class="font-semibold text-lg text-slate-200">
						{{ modelName }}
					</div>
					<div class="text-sm text-slate-400">{{ model.api }}</div>
				</div>
			</div>
			
			<!-- Pricing -->
			<div class="text-right">
				<div class="text-sm font-mono">
					<span class="text-green-400">${{ formattedPricing.input }}</span> in + 
					<span class="text-red-400">${{ formattedPricing.output }}</span> out
				</div>
				<div class="text-xs text-slate-400">per 1M tokens</div>
			</div>
		</div>

		<!-- Features Section -->
		<ModelFeatureBadges 
			v-if="features" 
			:features="features" 
			:compact="compact"
			class="mb-3"
		/>

		<!-- Stats Section -->
		<div v-if="!compact" class="grid grid-cols-2 gap-4 text-xs">
			<div>
				<div class="text-slate-400">Context Window</div>
				<div class="font-mono text-slate-200">
					{{ formatNumber(model.details?.context) }} tokens
				</div>
			</div>
			<div>
				<div class="text-slate-400">Rate Limit</div>
				<div class="font-mono text-slate-200">
					{{ formatNumber(model.details?.rate_limits?.requests_per_minute) }}/min
				</div>
			</div>
		</div>

		<!-- Compact Stats -->
		<div v-else class="flex items-center gap-3 text-xs text-slate-400">
			<div class="flex items-center gap-1">
				<FaSolidFile class="w-3 h-3" />
				<span>{{ formatNumber(model.details?.context) }} tokens</span>
			</div>
			<div class="flex items-center gap-1">
				<FaSolidGaugeHigh class="w-3 h-3" />
				<span>{{ formatNumber(model.details?.rate_limits?.requests_per_minute) }}/min</span>
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import { FaSolidBrain, FaSolidFile, FaSolidGaugeHigh } from "danx-icon";
import { computed } from "vue";
import ModelFeatureBadges from "./ModelFeatureBadges.vue";
import { 
	type ModelInfo, 
	getModelFeatures
} from "./modelUtils";

const props = defineProps<{
	model: ModelInfo;
	isSelected?: boolean;
	clickable?: boolean;
	compact?: boolean;
}>();

defineEmits<{
	select: [];
}>();

const modelName = computed(() => props.model.details.name || props.model.name);

const features = computed(() => getModelFeatures(props.model));

const formattedPricing = computed(() => ({
	input: (props.model.details.input * 1000000).toFixed(2),
	output: (props.model.details.output * 1000000).toFixed(2)
}));

function formatNumber(num: number | undefined): string {
	if (!num) return '0';
	if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
	if (num >= 1000) return (num / 1000).toFixed(0) + 'K';
	return num.toLocaleString();
}
</script>