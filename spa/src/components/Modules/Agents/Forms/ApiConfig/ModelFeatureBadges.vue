<template>
	<div class="flex flex-wrap gap-1.5">
		<div
			v-for="feature in displayedFeatures"
			:key="feature.key"
			:class="[
				'flex items-center gap-1 px-2 py-1 text-xs rounded transition-all cursor-help',
				feature.bgClass,
				feature.textClass
			]"
		>
			<component :is="feature.icon" class="w-3 h-3" />
			<span>{{ feature.label }}</span>
			<QTooltip class="bg-slate-900 text-slate-200 shadow-xl max-w-xs">
				<div class="p-2">
					<div class="font-semibold mb-1">{{ feature.label }}</div>
					<div class="text-xs">{{ feature.description }}</div>
				</div>
			</QTooltip>
		</div>
	</div>
</template>

<script setup lang="ts">
import {
	FaSolidLightbulb,
	FaSolidImage,
	FaSolidWrench,
	FaSolidPlay,
	FaSolidCode,
	FaSolidMicrophone,
	FaSolidGraduationCap,
	FaSolidFlask,
	FaSolidRobot,
	FaSolidTemperatureHigh
} from "danx-icon";
import { QTooltip } from "quasar";
import { computed } from "vue";

interface ModelFeatures {
	streaming?: boolean;
	function_calling?: boolean;
	structured_outputs?: boolean;
	fine_tuning?: boolean;
	distillation?: boolean;
	predicted_outputs?: boolean;
	image_input?: boolean;
	audio_input?: boolean;
	reasoning?: boolean;
	temperature?: boolean;
}

const props = defineProps<{
	features: ModelFeatures | null;
	compact?: boolean;
}>();

interface FeatureDisplay {
	key: string;
	label: string;
	icon: any;
	description: string;
	bgClass: string;
	textClass: string;
	show: boolean;
}

const featureDefinitions: FeatureDisplay[] = [
	{
		key: 'reasoning',
		label: 'Reasoning',
		icon: FaSolidLightbulb,
		description: 'Advanced problem-solving with step-by-step thinking. Models can break down complex problems and show their thought process.',
		bgClass: 'bg-green-600/20',
		textClass: 'text-green-300',
		show: props.features?.reasoning || false
	},
	{
		key: 'imageInput',
		label: 'Vision',
		icon: FaSolidImage,
		description: 'Can analyze and understand images, diagrams, charts, and other visual content.',
		bgClass: 'bg-blue-600/20',
		textClass: 'text-blue-300',
		show: props.features?.image_input || false
	},
	{
		key: 'functionCalling',
		label: 'Tools',
		icon: FaSolidWrench,
		description: 'Can execute functions and use external tools to extend capabilities beyond text generation.',
		bgClass: 'bg-purple-600/20',
		textClass: 'text-purple-300',
		show: props.features?.function_calling || false
	},
	{
		key: 'streaming',
		label: 'Streaming',
		icon: FaSolidPlay,
		description: 'Real-time response generation that shows text as it\'s being created for better user experience.',
		bgClass: 'bg-orange-600/20',
		textClass: 'text-orange-300',
		show: props.features?.streaming || false
	},
	{
		key: 'structuredOutputs',
		label: 'JSON',
		icon: FaSolidCode,
		description: 'Guaranteed structured output generation for reliable JSON responses and API integration.',
		bgClass: 'bg-cyan-600/20',
		textClass: 'text-cyan-300',
		show: props.features?.structured_outputs || false
	},
	{
		key: 'audioInput',
		label: 'Audio',
		icon: FaSolidMicrophone,
		description: 'Can process and understand audio inputs including speech and sounds.',
		bgClass: 'bg-pink-600/20',
		textClass: 'text-pink-300',
		show: props.features?.audio_input || false
	},
	{
		key: 'fineTuning',
		label: 'Fine-tune',
		icon: FaSolidGraduationCap,
		description: 'Model can be customized with your own training data for specialized tasks.',
		bgClass: 'bg-indigo-600/20',
		textClass: 'text-indigo-300',
		show: props.features?.fine_tuning || false
	},
	{
		key: 'distillation',
		label: 'Distill',
		icon: FaSolidFlask,
		description: 'Can be used to train smaller, faster models while preserving capabilities.',
		bgClass: 'bg-amber-600/20',
		textClass: 'text-amber-300',
		show: props.features?.distillation || false
	},
	{
		key: 'predictedOutputs',
		label: 'Predicted',
		icon: FaSolidRobot,
		description: 'Optimized for generating predicted outputs based on patterns in your data.',
		bgClass: 'bg-emerald-600/20',
		textClass: 'text-emerald-300',
		show: props.features?.predicted_outputs || false
	},
	{
		key: 'temperature',
		label: 'Temperature',
		icon: FaSolidTemperatureHigh,
		description: 'Supports creativity control via temperature settings for varied output styles.',
		bgClass: 'bg-red-600/20',
		textClass: 'text-red-300',
		show: props.features?.temperature !== false
	}
];

const displayedFeatures = computed(() => {
	const shown = featureDefinitions.filter(f => f.show);
	return props.compact ? shown.slice(0, 5) : shown;
});
</script>