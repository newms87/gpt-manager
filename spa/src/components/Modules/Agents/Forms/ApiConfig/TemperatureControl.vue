<template>
	<div class="temperature-control">
		<div class="mb-3 flex items-center gap-2">
			<FaSolidSliders class="w-5 h-5 text-orange-400" />
			<h3 class="text-lg font-semibold text-slate-200">Temperature Control</h3>
			<QIcon name="info" class="w-4 h-4 text-slate-400 cursor-help">
				<QTooltip class="bg-slate-800 text-slate-200 shadow-xl max-w-md">
					<div class="p-3">
						<div class="font-semibold mb-2 text-orange-300">Creativity vs Consistency</div>
						<p class="text-sm mb-2">Controls the randomness of the AI's responses:</p>
						<ul class="text-xs space-y-1">
							<li><strong>0.0-0.3:</strong> Very focused and deterministic</li>
							<li><strong>0.4-0.7:</strong> Balanced creativity and consistency</li>
							<li><strong>0.8-1.2:</strong> More creative and varied responses</li>
							<li><strong>1.3-2.0:</strong> Highly creative, less predictable</li>
						</ul>
						<p class="text-xs mt-2 text-slate-400">Note: Reasoning models don't support temperature control</p>
					</div>
				</QTooltip>
			</QIcon>
		</div>
		
		<div class="p-4 bg-slate-800/50 rounded-lg border border-slate-700">
			<div class="space-y-2">
				<label class="text-sm font-medium text-slate-300 flex items-center justify-between">
					<span>Temperature: {{ model }}</span>
					<span class="text-xs text-slate-400">
						{{ temperatureLabel }}
					</span>
				</label>
				<SliderNumberField
					v-model="model"
					:min="0"
					:max="2"
					:step="0.1"
					dark
					class="px-3"
				/>
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import { FaSolidSliders } from "danx-icon";
import { SliderNumberField } from "quasar-ui-danx";
import { QIcon, QTooltip } from "quasar";
import { computed } from "vue";

const model = defineModel<number>({ required: true });

const temperatureLabel = computed(() => {
	const temp = model.value;
	if (temp <= 0.3) return 'Very Focused';
	if (temp <= 0.7) return 'Balanced';
	if (temp <= 1.2) return 'Creative';
	return 'Highly Creative';
});
</script>