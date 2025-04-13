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
					:delay="500"
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
					@update:selected="emitUpdate"
				/>
				<!-- Case Sensitivity Option -->
				<QToggle
					v-if="allowCaseSensitive"
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
					v-else
					v-model="filterCondition.value"
					placeholder="Enter value..."
					@update:model-value="emitUpdate"
				/>
			</template>
		</div>
	</div>
</template>

<script setup lang="ts">
import { FilterCondition, FragmentSelector } from "@/types";
import {
	FaSolidBarcode as MetaIcon,
	FaSolidDatabase as JsonIcon,
	FaSolidFile as FilesIcon,
	FaSolidT as TextIcon
} from "danx-icon";
import { ActionButton, SelectionMenuField, TextField } from "quasar-ui-danx";
import { computed } from "vue";
import { FragmentSelectorConfigField } from "./index";

const emit = defineEmits(["update:model-value", "remove"]);

const filterCondition = defineModel<FilterCondition>();

// Make dataType a computed property instead of a ref
const dataType = computed<string>(() => {
	if (filterCondition.value.fragment_selector &&
		["json_content", "meta"].includes(filterCondition.value.field)) {
		const type = determineDataTypeFromFragmentSelector(filterCondition.value.fragment_selector);
		// Return a safe default if we couldn't determine the type
		return ["string", "boolean", "number", "date", "array"].includes(type) ? type : "string";
	} else if (filterCondition.value.field === "text_content") {
		return "string";
	} else if (filterCondition.value.field === "storedFiles") {
		return "array";
	} else {
		return "string"; // Default to string rather than unknown
	}
});

const allowCaseSensitive = computed(() => ["string", "array"].includes(dataType.value) && ["contains", "equals", "regex"].includes(filterCondition.value.operator));

// All available operators
const operatorOptions = {
	string: [
		{ label: "Contains", value: "contains" },
		{ label: "Equals", value: "equals" },
		{ label: "Alphanumerically Before", value: "less_than" },
		{ label: "Alphanumerically After", value: "greater_than" },
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
	]
};

// Compute available operators based on data type
const availableOperators = computed(() => {
	return operatorOptions[dataType.value] || [];
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

	// Reset the operator and value to the defaults
	filterCondition.value.operator = availableOperators.value[0].value;
	filterCondition.value.value = "";

	emitUpdate();
}

// Handle fragment selector change
function handleFragmentSelectorChange() {
	// If the operators have changed, reset the operator and value
	if (!availableOperators.value.some(op => op.value === filterCondition.value.operator)) {
		filterCondition.value.operator = availableOperators.value[0].value;
		filterCondition.value.value = "";
	}

	emitUpdate();
}

// Extract data type from fragment selector
function determineDataTypeFromFragmentSelector(fragmentSelector: FragmentSelector): string | null {
	try {
		// Find first leaf node in the fragment selector
		const leafNode = findFirstLeafNode(fragmentSelector);
		if (!leafNode) return null;

		const type = leafNode.type || null;
		const format = leafNode.format;

		// Special case for dates
		if (type === "string" && format === "date") {
			return "date";
		}

		return type;
	} catch (error) {
		console.error("Error determining data type from fragment selector:", error);
		return null;
	}
}

// Find the first leaf node in a fragment selector
function findFirstLeafNode(fragmentSelector: FragmentSelector): FragmentSelector {
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
	// Ensure the condition has a type property set to 'condition'
	filterCondition.value.type = "condition";

	emit("update:model-value", filterCondition.value);
}
</script>
