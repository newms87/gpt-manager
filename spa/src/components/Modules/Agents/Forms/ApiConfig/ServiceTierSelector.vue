<template>
	<div class="service-tier-selector">
		<div class="mb-3 flex items-center gap-2">
			<FaSolidServer class="w-5 h-5 text-blue-400" />
			<h3 class="text-lg font-semibold text-slate-200">Service Tier</h3>
			<QIcon name="info" class="w-4 h-4 text-slate-400 cursor-help">
				<QTooltip class="bg-slate-800 text-slate-200 shadow-xl max-w-md">
					<div class="p-3">
						<div class="font-semibold mb-2 text-blue-300">Processing Priority & Performance</div>
						<p class="text-sm mb-2">Choose how your requests are processed:</p>
						<ul class="text-xs space-y-1">
							<li><strong class="text-green-300">Auto:</strong> System automatically selects optimal tier</li>
							<li><strong class="text-blue-300">Default:</strong> Standard processing queue with predictable latency</li>
							<li><strong class="text-purple-300">Flex:</strong> Variable capacity processing for cost optimization</li>
						</ul>
						<p class="text-xs mt-2 text-slate-400">Higher tiers may have different pricing and performance characteristics</p>
					</div>
				</QTooltip>
			</QIcon>
		</div>
		
		<div class="grid grid-cols-3 gap-3">
			<div
				v-for="tier in serviceTiers"
				:key="tier.value"
				:class="[
					'p-4 rounded-lg border-2 cursor-pointer transition-all text-center group',
					model === tier.value
						? 'border-blue-500 bg-blue-500/10 text-blue-200 shadow-lg'
						: 'border-slate-600 bg-slate-700/50 text-slate-300 hover:border-slate-500 hover:bg-slate-700/70'
				]"
				@click="model = tier.value"
			>
				<div class="flex items-center justify-center mb-2">
					<component :is="tier.icon" class="w-6 h-6" />
				</div>
				<div class="font-semibold mb-1">{{ tier.label }}</div>
				<div class="text-xs opacity-75">{{ tier.description }}</div>
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import { FaSolidServer, FaSolidGear, FaSolidCloud, FaSolidBolt } from "danx-icon";
import { QIcon, QTooltip } from "quasar";

const model = defineModel<string>({ required: true });

const serviceTiers = [
	{
		value: 'auto',
		label: 'Auto',
		description: 'Automatic selection',
		icon: FaSolidGear
	},
	{
		value: 'default',
		label: 'Default',
		description: 'Standard processing',
		icon: FaSolidCloud
	},
	{
		value: 'flex',
		label: 'Flex',
		description: 'Variable capacity',
		icon: FaSolidBolt
	}
];
</script>