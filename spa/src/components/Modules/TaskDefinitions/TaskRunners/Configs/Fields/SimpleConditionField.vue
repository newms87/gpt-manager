<template>
	<div class="simple-condition p-3 border rounded-lg bg-slate-700">
		<!-- Delete button -->
		<div class="flex-x mb-4">
			<div class="flex-grow">
				<QTabs
					v-model="filterCondition.field"
					class="tab-buttons border-sky-900 !w-[20rem] bg-sky-950"
					indicator-color="sky-900"
					@update:model-value="handleFieldChange"
				>
					<QTab name="text_content">
						<TextIcon class="w-4" />
						<QTooltip>Filter on text</QTooltip>
					</QTab>
					<QTab name="json_content">
						<JsonIcon class="w-4" />
						<QTooltip>Filter on JSON Content</QTooltip>
					</QTab>
					<QTab name="meta">
						<MetaIcon class="w-4" />
						<QTooltip>Filter on metadata</QTooltip>
					</QTab>
					<QTab name="storedFiles">
						<FilesIcon class="w-4" />
						<QTooltip>Filter on files</QTooltip>
					</QTab>
				</QTabs>
			</div>

			<ActionButton type="trash" color="gray" size="sm" @click="$emit('remove')" />
		</div>

		<div class="grid grid-cols-1 gap-4">
			<!-- Fragment Selector -->
			<div v-if="['json_content', 'meta'].includes(filterCondition.field)">
				<FragmentSelectorConfigField
					v-model="filterCondition.fragment_selector"
					@update:model-value="handleFragmentSelectorChange"
				/>
			</div>

			<!-- Operator Selection -->
			<div class="flex-x gap-x-4">
				<SelectionMenuField
					v-model:selected="filterCondition.operator"
					selectable
					selection-type="string"
					:select-text="getOperatorLabel(filterCondition.operator)"
					label-class="hidden"
					:options="availableOperators"
					@update:model-value="emitUpdate"
				/>
				<!-- Case Sensitivity Option -->
				<QToggle
					v-if="['contains', 'equals', 'regex'].includes(filterCondition.operator) && dataType === 'string'"
					v-model="filterCondition.case_sensitive"
					label="Case Sensitive"
					@update:model-value="emitUpdate"
				/>
			</div>

			<!-- Value Input (not shown for exists operator) -->
			<template v-if="filterCondition.operator !== 'exists' && dataType !== 'boolean'">
				<!-- Date Value -->
				<QInput
					v-if="dataType === 'date'"
					v-model="filterCondition.value"
					type="date"
					placeholder="Select date..."
					filled
					@update:model-value="emitUpdate"
				/>

				<!-- Number Value -->
				<QInput
					v-else-if="dataType === 'number'"
					v-model="filterCondition.value"
					type="number"
					placeholder="Enter number..."
					filled
					@update:model-value="emitUpdate"
				/>

				<!-- Default String Value -->
				<TextField
					v-else-if="dataType === 'string' || dataType === 'array'"
					v-model="filterCondition.value"
					placeholder="Enter value..."
					@update:model-value="emitUpdate"
				/>

				<!-- any other value is an error -->
				<QBanner v-else class="bg-red-800 text-red-300 rounded">
					Invalid data type for value input.
				</QBanner>

			</template>
		</div>
	</div>
</template>

<script setup lang="ts">
import { FilterCondition } from "@/types";
import {
	FaSolidBarcode as MetaIcon,
	FaSolidDatabase as JsonIcon,
	FaSolidFile as FilesIcon,
	FaSolidT as TextIcon
} from "danx-icon";
import { ActionButton, SelectionMenuField, TextField } from "quasar-ui-danx";
import { computed, ref, watch } from "vue";
import { FragmentSelectorConfigField } from "./index";

const emit = defineEmits(["update:model-value", "remove"]);

const filterCondition = defineModel<FilterCondition>();

// Track the data type from the fragment selector
const dataType = ref<string>("string");

// All available operators
const operatorOptions = {
	string: [
		{ label: "Contains", value: "contains" },
		{ label: "Equals", value: "equals" },
		{ label: "Greater Than", value: "greater_than" },
		{ label: "Less Than", value: "less_than" },
		{ label: "Regex Match", value: "regex" },
		{ label: "Exists", value: "exists" }
	],
	boolean: [
		{ label: "Is True", value: "is_true" },
		{ label: "Is False", value: "is_false" },
		{ label: "Exists", value: "exists" }
	],
	number: [
		{ label: "Equals", value: "equals" },
		{ label: "Greater Than", value: "greater_than" },
		{ label: "Less Than", value: "less_than" },
		{ label: "Exists", value: "exists" }
	],
	date: [
		{ label: "Equals", value: "equals" },
		{ label: "After", value: "greater_than" },
		{ label: "Before", value: "less_than" },
		{ label: "Exists", value: "exists" }
	],
	array: [
		{ label: "Contains", value: "contains" },
		{ label: "Exists", value: "exists" }
	],
	unknown: [
		{ label: "Contains", value: "contains" },
		{ label: "Equals", value: "equals" },
		{ label: "Greater Than", value: "greater_than" },
		{ label: "Less Than", value: "less_than" },
		{ label: "Regex Match", value: "regex" },
		{ label: "Exists", value: "exists" }
	]
};

// Compute available operators based on data type
const availableOperators = computed(() => {
	return operatorOptions[dataType.value] || operatorOptions.unknown;
});

// Get operator label
function getOperatorLabel(operatorValue: string): string {
	const option = availableOperators.value.find(op => op.value === operatorValue);
	return option?.label || "Select Operator";
}

// Handle field change
function handleFieldChange() {
	// Reset fragment selector when changing field
	if (filterCondition.value.field !== "json_content" && filterCondition.value.field !== "meta") {
		filterCondition.value.fragment_selector = undefined;
	}

	// Set default data type based on field
	if (filterCondition.value.field === "text_content") {
		dataType.value = "string";
	} else if (filterCondition.value.field === "storedFiles") {
		dataType.value = "array";
	} else {
		dataType.value = "unknown"; // Will be updated when fragment selector changes
	}

	// Check if current operator is valid for the new field type
	if (!availableOperators.value.some(op => op.value === filterCondition.value.operator)) {
		// Reset to a valid operator
		if (dataType.value === "boolean") {
			filterCondition.value.operator = "is_true"; // Default boolean operator
		} else {
			filterCondition.value.operator = availableOperators.value[0].value;
		}
	}

	emitUpdate();
}

// Handle fragment selector change
function handleFragmentSelectorChange() {
	// Determine data type from fragment selector
	if (filterCondition.value.fragment_selector) {
		dataType.value = determineDataTypeFromFragmentSelector(filterCondition.value.fragment_selector);

		// If operator is not valid for this data type, reset it
		if (!availableOperators.value.some(op => op.value === filterCondition.value.operator)) {
			if (dataType.value === "boolean") {
				filterCondition.value.operator = "is_true";
			} else {
				filterCondition.value.operator = availableOperators.value[0].value;
			}
		}
	}

	emitUpdate();
}

// Extract data type from fragment selector
function determineDataTypeFromFragmentSelector(fragmentSelector: any): string {
	try {
		// Find first leaf node in the fragment selector
		const leafNode = findFirstLeafNode(fragmentSelector);
		if (!leafNode) return "unknown";

		const type = leafNode.type;
		const format = leafNode.format;

		// Special case for dates
		if (type === "string" && format === "date") {
			return "date";
		}

		return type || "unknown";
	} catch (error) {
		console.error("Error determining data type from fragment selector:", error);
		return "unknown";
	}
}

// Find the first leaf node in a fragment selector
function findFirstLeafNode(fragmentSelector: any): any {
	if (!fragmentSelector || !fragmentSelector.children || Object.keys(fragmentSelector.children).length === 0) {
		return null;
	}

	// Get the first child
	const childKey = Object.keys(fragmentSelector.children)[0];
	const firstChild = fragmentSelector.children[childKey];

	// If this child has no children of its own and has a type, it's a leaf node
	if (firstChild.type && (!firstChild.children || Object.keys(firstChild.children).length === 0)) {
		return firstChild;
	}

	// Otherwise, recursively search for a leaf node
	return findFirstLeafNode(firstChild);
}

// Emit the updated filter condition
function emitUpdate() {
	if (filterCondition.value) {
		// For boolean operators, we don't need a value (it's implied by the operator)
		if (["is_true", "is_false"].includes(filterCondition.value.operator)) {
			filterCondition.value.value = undefined;
		}

		emit("update:model-value", filterCondition.value);
	}
}

// Initialize with appropriate data type
watch(() => filterCondition.value, (newVal) => {
	if (newVal?.fragment_selector && ["json_content", "meta"].includes(newVal.field)) {
		dataType.value = determineDataTypeFromFragmentSelector(newVal.fragment_selector);
	} else if (newVal?.field === "text_content") {
		dataType.value = "string";
	} else if (newVal?.field === "storedFiles") {
		dataType.value = "array";
	} else {
		dataType.value = "unknown";
	}
}, { immediate: true, deep: true });
</script>
