<template>
	<div class="simple-condition p-3 border rounded-lg bg-white">
		<!-- Delete button -->
		<div class="flex justify-end mb-2">
			<QBtn
				icon="delete"
				color="negative"
				flat
				round
				dense
				@click="$emit('remove')"
			/>
		</div>

		<div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
			<!-- Field Selection -->
			<SelectionMenuField
				v-model="localCondition.field"
				selectable
				select-text="Field"
				:options="fieldOptions"
				class="mb-2"
				@update:selected="emitUpdate"
			/>

			<!-- Fragment Selector -->
			<div v-if="['json_content', 'meta'].includes(localCondition.field)" class="mb-2">
				<div class="text-sm text-slate-600 mb-1">Fragment Selector</div>
				<FragmentSelectorConfigField v-model="localCondition.fragment_selector" @update:model-value="emitUpdate" />
			</div>

			<!-- Operator Selection -->
			<SelectionMenuField
				v-model="localCondition.operator"
				selectable
				select-text="Operator"
				:options="operatorOptions"
				class="mb-2"
				@update:selected="emitUpdate"
			/>

			<!-- Value Input (not shown for exists operator) -->
			<QInput
				v-if="localCondition.operator !== 'exists'"
				v-model="localCondition.value"
				label="Value"
				class="mb-2"
				filled
				@update:model-value="emitUpdate"
			/>

			<!-- Case Sensitivity Option -->
			<QCheckbox
				v-if="['contains', 'equals', 'regex'].includes(localCondition.operator)"
				v-model="localCondition.case_sensitive"
				label="Case Sensitive"
				class="mb-2"
				@update:model-value="emitUpdate"
			/>
		</div>
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { FragmentSelectorConfigField } from "./index";
import { SelectionMenuField } from "quasar-ui-danx";

interface Condition {
	field: string;
	operator: string;
	value?: string;
	case_sensitive?: boolean;
	fragment_selector?: any;
}

const props = defineProps<{
	condition: Condition;
}>();

const emit = defineEmits<{
	remove: [];
	update: [Condition];
}>();

// Use computed property to handle two-way binding
const localCondition = computed({
	get: () => props.condition,
	set: (val) => emit('update', val)
});

// Field options for the filter conditions
const fieldOptions = [
	{ label: "Text Content", value: "text_content" },
	{ label: "JSON Content", value: "json_content" },
	{ label: "Metadata", value: "meta" },
	{ label: "Stored Files", value: "storedFiles" }
];

// Operator options for the filter conditions
const operatorOptions = [
	{ label: "Contains", value: "contains" },
	{ label: "Equals", value: "equals" },
	{ label: "Greater Than", value: "greater_than" },
	{ label: "Less Than", value: "less_than" },
	{ label: "Regex Match", value: "regex" },
	{ label: "Exists", value: "exists" }
];

// Emit update event when any value changes
function emitUpdate() {
	emit('update', localCondition.value);
}
</script>
