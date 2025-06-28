<template>
	<div class="agent-api-config-form">
		<!-- Responses API Header -->
		<div class="mb-6">
			<div class="mb-3 flex items-center gap-2">
				<FaSolidBrain class="w-5 h-5 text-green-400" />
				<h3 class="text-lg font-semibold text-slate-200">Responses API Configuration</h3>
				<QIcon name="info" class="w-4 h-4 text-slate-400 cursor-help">
					<QTooltip class="bg-slate-800 text-slate-200 shadow-xl max-w-md">
						<div class="p-2">
							<div class="font-semibold mb-2 text-blue-300">Advanced OpenAI Responses API</div>
							<p class="text-sm mb-2">The next-generation OpenAI API that supports:</p>
							<ul class="text-xs space-y-1 list-disc list-inside ml-2">
								<li><strong>Reasoning models:</strong> Advanced problem-solving capabilities</li>
								<li><strong>Service tiers:</strong> Performance and cost optimization</li>
								<li><strong>Real-time streaming:</strong> Word-by-word response generation</li>
								<li><strong>Function calling:</strong> Execute custom code and tools</li>
							</ul>
						</div>
					</QTooltip>
				</QIcon>
			</div>
			<p class="text-sm text-slate-400 mb-4">
				Configure advanced AI model settings for optimal performance and cost efficiency.
			</p>
		</div>

		<!-- Model Selection -->
		<div class="mb-6">
			<ModelSelector
				:model-value="localAgent.model"
				:available-models="availableModels"
				@update:model-value="updateField('model', $event)"
			/>
		</div>

		<!-- Temperature Control (only for models that support it) -->
		<div v-if="modelSupportsTemperature" class="mb-6">
			<TemperatureControl
				:model-value="apiOptions.temperature || 0.7"
				@update:model-value="updateApiOption('temperature', $event)"
			/>
		</div>

		<!-- Reasoning Configuration (only for reasoning models) -->
		<div v-if="modelSupportsReasoning" class="mb-6">
			<ReasoningControl
				:model-value="reasoningOptions"
				@update:model-value="updateReasoningOptions"
			/>
		</div>

		<!-- Service Tier (available for all models that support streaming) -->
		<div v-if="modelSupportsStreaming" class="mb-6">
			<ServiceTierSelector
				:model-value="apiOptions.service_tier || 'auto'"
				@update:model-value="updateApiOption('service_tier', $event)"
			/>
		</div>

		<!-- Streaming Toggle (available for models that support streaming) -->
		<div v-if="modelSupportsStreaming" class="mb-6">
			<StreamingToggle
				:model-value="apiOptions.stream || false"
				@update:model-value="updateApiOption('stream', $event)"
			/>
		</div>

		<!-- Save State Indicator -->
		<div class="flex items-center justify-between pt-4 border-t border-slate-700">
			<SaveStateIndicator :action="updateAction" />
			<div class="text-xs text-slate-400">
				Configuration is auto-saved as you make changes
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import { dxAgent } from "@/components/Modules/Agents";
import { controls } from "@/components/Modules/Agents/config/controls";
import { Agent, AgentApiOptions } from "@/types";
import { FaSolidBrain } from "danx-icon";
import { QIcon, QTooltip } from "quasar";
import { SaveStateIndicator } from "quasar-ui-danx";
import { computed, ref, watch } from "vue";

// Import sub-components
import ModelSelector from "./ApiConfig/ModelSelector.vue";
import { getModelFeatures, type ModelInfo } from "./ApiConfig/modelUtils";
import ReasoningControl from "./ApiConfig/ReasoningControl.vue";
import ServiceTierSelector from "./ApiConfig/ServiceTierSelector.vue";
import StreamingToggle from "./ApiConfig/StreamingToggle.vue";
import TemperatureControl from "./ApiConfig/TemperatureControl.vue";

const props = defineProps<{
	agent: Agent;
}>();

const emit = defineEmits<{
	"update:agent": [agent: Agent];
}>();

// Local reactive copy
const localAgent = ref<Agent>({ ...props.agent });

// Computed API options with proper defaults
const apiOptions = computed<AgentApiOptions>(() => ({
	temperature: localAgent.value.api_options?.temperature || 0.7,
	model: localAgent.value.model,
	reasoning: {
		effort: "medium",
		summary: "auto",
		...(localAgent.value.api_options?.reasoning || {})
	},
	service_tier: localAgent.value.api_options?.service_tier || "auto",
	stream: localAgent.value.api_options?.stream || false,
	...localAgent.value.api_options
}));

// Model options from controls
const availableModels = computed(() => controls.getFieldOptions("aiModels") as ModelInfo[]);

// Find selected model configuration
const selectedModelConfig = computed(() => {
	const model = availableModels.value.find(m => m.name === localAgent.value.model);
	if (!model) return null;

	return model;
});

// Get features for selected model
const selectedModelFeatures = computed(() => {
	if (!selectedModelConfig.value) return null;
	return getModelFeatures(selectedModelConfig.value);
});

// Feature support computed properties
const modelSupportsTemperature = computed(() => {
	return selectedModelFeatures.value?.temperature === true;
});

const modelSupportsReasoning = computed(() => {
	return selectedModelFeatures.value?.reasoning === true;
});

const modelSupportsStreaming = computed(() => {
	return selectedModelFeatures.value?.streaming === true;
});

// Reasoning options
const reasoningOptions = computed(() => ({
	effort: apiOptions.value.reasoning?.effort || "medium",
	summary: apiOptions.value.reasoning?.summary || "auto"
}));

// Update action
const updateAction = dxAgent.getAction("update-debounced");

// Watch for external changes
watch(() => props.agent, (newAgent) => {
	localAgent.value = { ...newAgent };
}, { deep: true });

// Update basic field
function updateField(field: keyof Agent, value: any) {
	(localAgent.value as any)[field] = value;
	saveChanges();
}

// Update API option
function updateApiOption(key: keyof AgentApiOptions, value: any) {
	if (!localAgent.value.api_options) {
		localAgent.value.api_options = {};
	}
	(localAgent.value.api_options as any)[key] = value;
	saveChanges();
}

// Update reasoning options
function updateReasoningOptions(value: any) {
	if (!localAgent.value.api_options) {
		localAgent.value.api_options = {};
	}
	localAgent.value.api_options.reasoning = value;
	saveChanges();
}

// Save changes
function saveChanges() {
	emit("update:agent", { ...localAgent.value });
	updateAction.trigger(localAgent.value, {
		api_options: localAgent.value.api_options,
		model: localAgent.value.model
	});
}
</script>

<style lang="scss" scoped>
.agent-api-config-form {
	.q-field {
		.q-field__control {
			@apply bg-slate-800 border border-slate-600 rounded;
		}
	}
}
</style>
