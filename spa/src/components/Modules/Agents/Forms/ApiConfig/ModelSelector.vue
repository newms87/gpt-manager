<template>
	<div class="model-selector">
		<div class="mb-3 flex items-center gap-2">
			<FaSolidCube class="w-5 h-5 text-purple-400" />
			<h3 class="text-lg font-semibold text-slate-200">Model Selection</h3>
			<QIcon name="info" class="w-4 h-4 text-slate-400 cursor-help">
				<QTooltip class="bg-slate-800 text-slate-200 shadow-xl max-w-lg">
					<div class="p-3">
						<div class="font-semibold mb-2 text-purple-300">Choose Your AI Model</div>
						<p class="text-sm mb-3">Each model has different capabilities, performance characteristics, and pricing:</p>
						<div class="grid grid-cols-1 gap-2 text-xs">
							<div><strong class="text-green-300">o-series:</strong> Advanced reasoning models (no temperature control)</div>
							<div><strong class="text-blue-300">GPT-4o:</strong> Balanced performance with multimodal support</div>
							<div><strong class="text-yellow-300">Context Window:</strong> Maximum tokens the model can process</div>
							<div><strong class="text-red-300">Rate Limits:</strong> API request and token usage restrictions</div>
						</div>
					</div>
				</QTooltip>
			</QIcon>
		</div>

		<!-- Selected Model Display -->
		<div class="cursor-pointer" @click="showSelector = true">
			<ModelCard
				v-if="selectedModel"
				:model="selectedModel"
				:is-selected="true"
				:compact="true"
				clickable
			/>
			<div v-else class="p-4 bg-slate-800 rounded-lg border border-slate-600 text-slate-400">
				Select a model...
			</div>
		</div>

		<!-- Model Selection Modal -->
		<QDialog v-model="showSelector">
			<QCard class="bg-slate-800 text-slate-200 w-full max-w-4xl">
				<QCardSection class="flex items-center justify-between border-b border-slate-700">
					<div class="text-lg font-semibold">Select AI Model</div>
					<QBtn flat round icon="close" @click="showSelector = false" />
				</QCardSection>
				
				<QCardSection class="p-0">
					<div class="grid grid-cols-1 gap-4 p-4 max-h-[600px] overflow-y-auto">
						<ModelCard
							v-for="modelInfo in availableModels" 
							:key="modelInfo.name"
							:model="modelInfo"
							:is-selected="model === modelInfo.name"
							clickable
							@select="selectModel(modelInfo.name)"
						/>
					</div>
				</QCardSection>
			</QCard>
		</QDialog>
	</div>
</template>

<script setup lang="ts">
import { FaSolidCube } from "danx-icon";
import { QIcon, QTooltip, QDialog, QCard, QCardSection, QBtn } from "quasar";
import { computed, ref } from "vue";
import ModelCard from "./ModelCard.vue";

import { type ModelInfo } from "./modelUtils";

const model = defineModel<string>({ required: true });

const props = defineProps<{
	availableModels: ModelInfo[];
}>();

const showSelector = ref(false);

const selectedModel = computed(() => 
	props.availableModels.find(m => m.name === model.value)
);

function selectModel(value: string) {
	model.value = value;
	showSelector.value = false;
}
</script>