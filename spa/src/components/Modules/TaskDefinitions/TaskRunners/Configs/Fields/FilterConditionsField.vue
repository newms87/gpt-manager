<template>
	<div class="filter-conditions-field">
		<div class="text-lg font-medium mb-2">Filter Configuration</div>

		<!-- Group Operator Selection -->
		<div v-if="config.conditions && config.conditions.length > 1" class="mb-4">
			<div class="text-sm text-slate-600 mb-1">Group Operator</div>
			<QBtnToggle
				v-model="config.operator"
				:options="[
					{ label: 'AND (All conditions must match)', value: 'AND' },
					{ label: 'OR (Any condition may match)', value: 'OR' },
				]"
				class="w-full"
				spread
			/>
		</div>

		<!-- Conditions List -->
		<div class="conditions-list space-y-4">
			<div
				v-for="(condition, index) in config.conditions"
				:key="index"
				class="condition-card p-4 border rounded-lg bg-gray-50"
			>
				<!-- Nested Condition Group -->
				<template v-if="isNestedGroup(condition)">
					<div class="flex justify-between items-center mb-2">
						<div class="text-md font-medium">Condition Group</div>
						<div class="flex">
							<QBtn
								icon="delete"
								color="negative"
								flat
								round
								dense
								@click="removeCondition(index)"
							/>
						</div>
					</div>

					<div class="mb-2">
						<QBtnToggle
							v-model="condition.operator"
							:options="[
								{ label: 'AND', value: 'AND' },
								{ label: 'OR', value: 'OR' },
							]"
							class="w-full"
							spread
						/>
					</div>

					<!-- Recursive Rendering of Nested Conditions -->
					<div class="nested-conditions pl-4 border-l-2 border-blue-200">
						<div v-for="(nestedCondition, nestedIndex) in condition.conditions" :key="nestedIndex" class="mb-2">
							<!-- Simple Condition -->
							<div class="simple-condition p-3 border rounded-lg bg-white">
								<div class="flex justify-end mb-2">
									<QBtn
										icon="delete"
										color="negative"
										flat
										round
										dense
										@click="removeNestedCondition(condition, nestedIndex)"
									/>
								</div>

								<div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
									<!-- Field Selection -->
									<QSelect
										v-model="nestedCondition.field"
										:options="fieldOptions"
										label="Field"
										class="mb-2"
										filled
									/>

									<!-- Path Input -->
									<QInput
										v-if="['json_content', 'meta'].includes(nestedCondition.field)"
										v-model="nestedCondition.path"
										label="Path (use dot notation, e.g., 'category.name')"
										class="mb-2"
										filled
									/>

									<!-- Operator Selection -->
									<QSelect
										v-model="nestedCondition.operator"
										:options="operatorOptions"
										label="Operator"
										class="mb-2"
										filled
									/>

									<!-- Value Input (not shown for exists operator) -->
									<QInput
										v-if="nestedCondition.operator !== 'exists'"
										v-model="nestedCondition.value"
										label="Value"
										class="mb-2"
										filled
									/>

									<!-- Case Sensitivity Option -->
									<QCheckbox
										v-if="['contains', 'equals', 'regex'].includes(nestedCondition.operator)"
										v-model="nestedCondition.case_sensitive"
										label="Case Sensitive"
										class="mb-2"
									/>
								</div>
							</div>
						</div>

						<!-- Add Nested Condition Button -->
						<QBtn
							color="primary"
							label="Add Condition"
							icon="add"
							flat
							class="mt-2"
							@click="addNestedCondition(condition)"
						/>
					</div>
				</template>

				<!-- Simple Condition -->
				<template v-else>
					<div class="flex justify-end mb-2">
						<QBtn
							icon="delete"
							color="negative"
							flat
							round
							dense
							@click="removeCondition(index)"
						/>
					</div>

					<div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
						<!-- Field Selection -->
						<QSelect
							v-model="condition.field"
							:options="fieldOptions"
							label="Field"
							class="mb-2"
							filled
						/>

						<!-- Path Input -->
						<QInput
							v-if="['json_content', 'meta'].includes(condition.field)"
							v-model="condition.path"
							label="Path (use dot notation, e.g., 'category.name')"
							class="mb-2"
							filled
						/>

						<!-- Operator Selection -->
						<QSelect
							v-model="condition.operator"
							:options="operatorOptions"
							label="Operator"
							class="mb-2"
							filled
						/>

						<!-- Value Input (not shown for exists operator) -->
						<QInput
							v-if="condition.operator !== 'exists'"
							v-model="condition.value"
							label="Value"
							class="mb-2"
							filled
						/>

						<!-- Case Sensitivity Option -->
						<QCheckbox
							v-if="['contains', 'equals', 'regex'].includes(condition.operator)"
							v-model="condition.case_sensitive"
							label="Case Sensitive"
							class="mb-2"
						/>
					</div>
				</template>
			</div>
		</div>

		<!-- Action Buttons -->
		<div class="flex space-x-2 mt-4">
			<QBtn
				color="primary"
				label="Add Condition"
				icon="add"
				@click="addCondition"
			/>
			<QBtn
				color="secondary"
				label="Add Condition Group"
				icon="folder"
				@click="addConditionGroup"
			/>
		</div>
	</div>
</template>

<script setup lang="ts">
import { TaskDefinition } from "@/types";
import { ref, watch } from "vue";

const props = defineProps<{
	taskDefinition: TaskDefinition;
}>();

// Default filter config structure
const defaultConfig = {
	operator: "AND",
	conditions: []
};

// Initialize config from task definition or use default
const config = ref(props.taskDefinition.task_runner_config?.filter_config || defaultConfig);

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

// Check if a condition is a nested group
function isNestedGroup(condition: any): boolean {
	return condition.operator !== undefined && Array.isArray(condition.conditions);
}

// Add a new simple condition
function addCondition() {
	config.value.conditions.push({
		field: "text_content",
		operator: "contains",
		value: "",
		case_sensitive: false
	});
	updateTaskDefinition();
}

// Add a new condition group
function addConditionGroup() {
	config.value.conditions.push({
		operator: "AND",
		conditions: []
	});
	updateTaskDefinition();
}

// Add a condition to a nested group
function addNestedCondition(group: any) {
	group.conditions.push({
		field: "text_content",
		operator: "contains",
		value: "",
		case_sensitive: false
	});
	updateTaskDefinition();
}

// Remove a condition from the top level
function removeCondition(index: number) {
	config.value.conditions.splice(index, 1);
	updateTaskDefinition();
}

// Remove a condition from a nested group
function removeNestedCondition(group: any, index: number) {
	group.conditions.splice(index, 1);
	updateTaskDefinition();
}

// Update the task definition with the current config
function updateTaskDefinition() {
	if (!props.taskDefinition.task_runner_config) {
		props.taskDefinition.task_runner_config = {};
	}
	props.taskDefinition.task_runner_config.filter_config = config.value;
}

// Watch for changes to sync with task definition
watch(config, () => {
	updateTaskDefinition();
}, { deep: true });

// Initialize the task definition if needed
if (!props.taskDefinition.task_runner_config) {
	props.taskDefinition.task_runner_config = { filter_config: defaultConfig };
} else if (!props.taskDefinition.task_runner_config.filter_config) {
	props.taskDefinition.task_runner_config.filter_config = defaultConfig;
}
</script>

<style scoped>
.filter-conditions-field {
	padding: 1rem;
}

.condition-card {
	transition: all 0.3s ease;
}

.condition-card:hover {
	box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}
</style>
