<template>
	<BaseTaskRunnerConfig :task-definition="taskDefinition">
		<div class="p-4">
			<!-- Batch size field -->
			<NumberField
				v-model="batchSize"
				label="Batch Size"
				placeholder="Enter batch size..."
				:min="0"
				:max="1000"
				prepend-label
				class="mb-2"
				@update:model-value="debounceChange"
			/>
			<div class="text-xs text-gray-500 mb-6 ml-1">
				<p>Set the batch size for processing CSV rows:</p>
				<ul class="list-disc pl-5 mt-1">
					<li>0: All rows in one batch</li>
					<li>1: Each row as a separate artifact</li>
					<li>>1: Group rows into batches of specified size</li>
				</ul>
			</div>

			<!-- Selected columns field -->
			<TextField
				v-model="selectedColumnsInput"
				label="Selected Columns"
				placeholder="Column1, Column2, Column3"
				help-text="Specify columns to include (comma-separated). Leave empty to include all columns."
				@update:model-value="processColumnsInput"
			/>

			<div v-if="selectedColumnsArray.length > 0" class="mt-4 mb-2">
				<div class="text-sm font-medium mb-2">Selected columns:</div>
				<div class="flex flex-wrap gap-2">
					<div
						v-for="column in selectedColumnsArray"
						:key="column"
						class="bg-slate-700 text-slate-200 text-xs px-2 py-1.5 rounded-md flex items-center"
					>
						{{ column }}
						<ActionButton
							type="trash"
							color="gray"
							size="xs"
							tooltip="Remove column"
							class="ml-4"
							@click.stop="removeColumn(column)"
						/>
					</div>
				</div>
			</div>
		</div>
	</BaseTaskRunnerConfig>
</template>

<script setup lang="ts">
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions";
import { TaskDefinition } from "@/types";
import { useDebounceFn } from "@vueuse/core";
import { ActionButton, NumberField, TextField } from "quasar-ui-danx";
import { computed, ref } from "vue";
import BaseTaskRunnerConfig from "./BaseTaskRunnerConfig";

export interface LoadCsvTaskRunnerConfig {
	batch_size: number;
	selected_columns: string[];
}

const props = defineProps<{
	taskDefinition: TaskDefinition;
}>();

const config = computed(() => (props.taskDefinition.task_runner_config || {}) as LoadCsvTaskRunnerConfig);
const batchSize = ref(config.value.batch_size ?? 1);
const selectedColumns = ref(config.value.selected_columns ?? []);
const selectedColumnsInput = ref(selectedColumns.value.join(", "));

// Computed property to get the array of selected columns
const selectedColumnsArray = computed(() => {
	return selectedColumns.value;
});

/**
 * Process the columns input string into an array of column names
 * This handles the input more robustly by:
 * - Trimming whitespace from each column name
 * - Removing empty entries
 * - Removing duplicates
 * - Properly handling comma-separated lists
 */
function processColumnsInput(value: string) {
	// Split by commas, trim whitespace, and filter out empty strings
	const columns = value.split(",")
		.map(col => col.trim())
		.filter(col => col !== "");

	// Remove duplicates while preserving order
	const uniqueColumns = [...new Set(columns)];

	// Update the model
	selectedColumns.value = uniqueColumns;

	// Trigger the debounced change handler
	debounceChange();
}

// Function to remove a column
function removeColumn(column: string) {
	// Remove the column from the selected columns array
	selectedColumns.value = selectedColumns.value.filter(col => col !== column);

	// Update the input field value
	selectedColumnsInput.value = selectedColumns.value.join(", ");

	// Trigger the update
	debounceChange();
}

const updateTaskDefinitionAction = dxTaskDefinition.getAction("update");

const debounceChange = useDebounceFn(() => {
	updateTaskDefinitionAction.trigger(props.taskDefinition, {
		task_runner_config: {
			...config.value,
			batch_size: Number(batchSize.value) || 1,
			selected_columns: selectedColumns.value
		}
	});
}, 500);
</script>
