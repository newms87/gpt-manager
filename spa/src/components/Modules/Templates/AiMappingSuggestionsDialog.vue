<template>
	<QDialog v-model="isVisible" class="z-50">
		<QCard class="w-[550px] max-w-[90vw] bg-white">
			<QCardSection class="bg-slate-100 border-b border-slate-200">
				<div class="flex items-center gap-2">
					<AiIcon class="w-5 h-5 text-purple-600" />
					<div class="text-lg font-semibold text-slate-800">AI Mapping Suggestions</div>
				</div>
				<div class="text-slate-500 text-sm mt-1">
					Review and accept the suggested variable-to-schema mappings below.
				</div>
			</QCardSection>

			<QCardSection class="max-h-[60vh] overflow-y-auto">
				<!-- No suggestions state -->
				<div
					v-if="suggestions.length === 0"
					class="text-center py-8 text-slate-500"
				>
					<NoMatchIcon class="w-12 h-12 mx-auto mb-3 opacity-50" />
					<p class="font-medium">No suggestions found</p>
					<p class="text-sm mt-1">
						AI could not find suitable matches for your unmapped variables.
					</p>
				</div>

				<!-- Suggestions list -->
				<div v-else class="space-y-3">
					<div
						v-for="suggestion in suggestions"
						:key="suggestion.variable_id"
						:class="[
							'rounded-lg border p-3 transition-all',
							selectedIds.has(suggestion.variable_id)
								? 'border-purple-300 bg-purple-50'
								: 'border-slate-200 bg-white hover:border-slate-300'
						]"
					>
						<div class="flex items-start gap-3">
							<!-- Checkbox -->
							<QCheckbox
								:model-value="selectedIds.has(suggestion.variable_id)"
								:disable="!suggestion.suggested_fragment_id"
								color="purple"
								class="mt-0.5"
								@update:model-value="toggleSelection(suggestion.variable_id)"
							/>

							<div class="flex-grow min-w-0">
								<!-- Variable name and suggested fragment -->
								<div class="flex items-center gap-2 flex-wrap">
									<code class="text-sky-600 font-mono text-sm font-medium">
										{{ formatVariableName(suggestion.variable_name) }}
									</code>
									<ArrowIcon class="w-3 h-3 text-slate-400" />
									<span
										v-if="suggestion.suggested_fragment_name"
										class="text-green-600 font-medium text-sm"
									>
										{{ suggestion.suggested_fragment_name }}
									</span>
									<span v-else class="text-slate-400 text-sm italic">
										No match found
									</span>
								</div>

								<!-- Confidence badge -->
								<div class="flex items-center gap-2 mt-2">
									<span
										class="text-xs px-2 py-0.5 rounded-full font-medium"
										:class="getConfidenceStyles(suggestion.confidence)"
									>
										{{ getConfidenceLabel(suggestion.confidence) }}
										({{ Math.round(suggestion.confidence * 100) }}%)
									</span>
								</div>

								<!-- Reasoning -->
								<div
									v-if="suggestion.reasoning"
									class="text-xs text-slate-500 mt-2 leading-relaxed"
								>
									{{ suggestion.reasoning }}
								</div>
							</div>
						</div>
					</div>
				</div>
			</QCardSection>

			<QCardActions align="right" class="bg-slate-50 border-t border-slate-200 px-4 py-3">
				<div class="flex items-center gap-3">
					<span v-if="validSuggestions.length > 0" class="text-sm text-slate-500">
						{{ selectedIds.size }} of {{ validSuggestions.length }} selected
					</span>
					<ActionButton
						type="cancel"
						label="Cancel"
						color="slate"
						@click="close"
					/>
					<ActionButton
						v-if="validSuggestions.length > 0"
						type="check"
						:label="`Accept Selected (${selectedIds.size})`"
						color="purple-invert"
						:disabled="selectedIds.size === 0"
						@click="acceptSelected"
					/>
				</div>
			</QCardActions>
		</QCard>
	</QDialog>
</template>

<script setup lang="ts">
import {
	FaSolidArrowRight as ArrowIcon,
	FaSolidBrain as AiIcon,
	FaSolidCircleQuestion as NoMatchIcon
} from "danx-icon";
import { QCard, QCardActions, QCardSection, QCheckbox, QDialog } from "quasar";
import { ActionButton } from "quasar-ui-danx";
import { computed, ref, watch } from "vue";

/**
 * Suggestion item from the backend
 */
export interface MappingSuggestion {
	variable_id: number;
	variable_name: string;
	suggested_fragment_id: number | null;
	suggested_fragment_name: string | null;
	confidence: number;
	reasoning: string;
}

const isVisible = defineModel<boolean>({ required: true });

const props = defineProps<{
	suggestions: MappingSuggestion[];
}>();

const emit = defineEmits<{
	accept: [acceptedSuggestions: MappingSuggestion[]];
}>();

// Track selected suggestion IDs
const selectedIds = ref<Set<number>>(new Set());

/**
 * Valid suggestions (those with a suggested fragment)
 */
const validSuggestions = computed(() =>
	props.suggestions.filter(s => s.suggested_fragment_id !== null)
);

/**
 * Initialize selections when suggestions change
 * Pre-select high confidence matches (>= 0.7)
 */
watch(
	() => props.suggestions,
	(suggestions) => {
		selectedIds.value = new Set(
			suggestions
				.filter(s => s.suggested_fragment_id !== null && s.confidence >= 0.7)
				.map(s => s.variable_id)
		);
	},
	{ immediate: true }
);

/**
 * Toggle selection of a suggestion
 */
function toggleSelection(variableId: number) {
	const newSet = new Set(selectedIds.value);
	if (newSet.has(variableId)) {
		newSet.delete(variableId);
	} else {
		newSet.add(variableId);
	}
	selectedIds.value = newSet;
}

/**
 * Format variable name with mustache syntax
 */
function formatVariableName(name: string): string {
	return `{{${name}}}`;
}

/**
 * Get confidence level label
 */
function getConfidenceLabel(confidence: number): string {
	if (confidence >= 0.9) return "Very High";
	if (confidence >= 0.7) return "High";
	if (confidence >= 0.5) return "Medium";
	if (confidence >= 0.3) return "Low";
	return "Very Low";
}

/**
 * Get confidence badge styles based on score
 */
function getConfidenceStyles(confidence: number): string {
	if (confidence >= 0.9) return "bg-green-100 text-green-700";
	if (confidence >= 0.7) return "bg-emerald-100 text-emerald-700";
	if (confidence >= 0.5) return "bg-yellow-100 text-yellow-700";
	if (confidence >= 0.3) return "bg-orange-100 text-orange-700";
	return "bg-red-100 text-red-700";
}

/**
 * Close the dialog
 */
function close() {
	isVisible.value = false;
}

/**
 * Accept selected suggestions and emit
 */
function acceptSelected() {
	const accepted = props.suggestions.filter(s => selectedIds.value.has(s.variable_id));
	emit("accept", accepted);
	close();
}
</script>
