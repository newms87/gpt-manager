<template>
	<div class="simple-condition p-3 border rounded-lg bg-slate-700">
		<!-- Delete button -->
		<div class="flex-x mb-4">
			<div class="flex-grow">
				<QTabs
					v-model="filterCondition.field"
					class="tab-buttons border-sky-900 !w-[20rem] bg-sky-950"
					indicator-color="sky-900"
					@update:model-value="emitUpdate"
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

			<ActionButton
				type="trash"
				color="gray"
				size="sm"
				@click="$emit('remove')"
			/>
		</div>

		<div class="grid grid-cols-1 gap-4">

			<!-- Fragment Selector -->
			<div v-if="['json_content', 'meta'].includes(filterCondition.field)">
				<FragmentSelectorConfigField v-model="filterCondition.fragment_selector" @update:model-value="emitUpdate" />
			</div>

			<!-- Operator Selection -->
			<div class="flex-x gap-x-4">
				<SelectionMenuField
					v-model:selected="filterCondition.operator"
					selectable
					selection-type="string"
					:select-text="operatorOptions.find(o => o.value === filterCondition.operator)?.label"
					label-class="hidden"
					:options="operatorOptions"
					@update:model-value="emitUpdate"
				/>
				<!-- Case Sensitivity Option -->
				<QToggle
					v-if="['contains', 'equals', 'regex'].includes(filterCondition.operator)"
					v-model="filterCondition.case_sensitive"
					label="Case Sensitive"
					@update:model-value="emitUpdate"
				/>
			</div>

			<!-- Value Input (not shown for exists operator) -->
			<TextField
				v-if="filterCondition.operator !== 'exists'"
				v-model="filterCondition.value"
				placeholder="Enter value..."
				@update:model-value="emitUpdate"
			/>
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
import { FragmentSelectorConfigField } from "./index";

const emit = defineEmits<{ "update:model-value": FilterCondition; remove: void; }>();

const filterCondition = defineModel<FilterCondition>();

// Operator options for the filter conditions
const operatorOptions = [
	{ label: "Contains", value: "contains" },
	{ label: "Equals", value: "equals" },
	{ label: "Greater Than", value: "greater_than" },
	{ label: "Less Than", value: "less_than" },
	{ label: "Regex Match", value: "regex" },
	{ label: "Exists", value: "exists" }
];

// Emit the updated filter condition
function emitUpdate() {
	emit("update:model-value", filterCondition.value);
}
</script>
