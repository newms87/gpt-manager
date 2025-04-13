<template>
	<div class="filter-conditions-field p-4 bg-slate-800 rounded-lg">
		<!-- Group Operator Selection -->
		<AndOrConditionTabs
			v-if="config.conditions?.length > 1"
			v-model="config.operator"
			class="mb-4"
			@update:model-value="updateTaskDefinition"
		/>

		<!-- Conditions List -->
		<ListTransition v-if="config.conditions.length > 0" class="conditions-list space-y-4 mb-4">
			<template v-for="(condition, index) in config.conditions" :key="index">
				<!-- For Condition Groups -->
				<ConditionGroupField
					v-if="isNestedGroup(condition)"
					:group="condition as FilterConditionGroup"
					@remove="removeCondition(index)"
					@update="updateCondition(index, $event)"
				/>

				<!-- For Simple Conditions -->
				<SimpleConditionField
					v-else
					:model-value="condition as FilterCondition"
					@remove="removeCondition(index)"
					@update:model-value="updateCondition(index, $event)"
				/>
			</template>
		</ListTransition>

		<!-- Action Buttons -->
		<div class="flex space-x-2">
			<ActionButton
				type="create"
				color="blue"
				label="Add Condition"
				@click="addCondition"
			/>
			<ActionButton
				type="folder"
				color="green"
				label="Add Condition Group"
				@click="addConditionGroup"
			/>
		</div>
	</div>
</template>

<script setup lang="ts">
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions";
import { FilterCondition, FilterConditionGroup, FilterConfig, TaskDefinition } from "@/types";
import { ActionButton, ListTransition } from "quasar-ui-danx";
import { ref } from "vue";
import AndOrConditionTabs from "./AndOrConditionTabs";
import ConditionGroupField from "./ConditionGroupField.vue";
import SimpleConditionField from "./SimpleConditionField.vue";

const props = defineProps<{
	taskDefinition: TaskDefinition;
}>();

// Default filter config structure
const defaultConfig: FilterConfig = {
	operator: "AND",
	conditions: []
};

// Initialize config from task definition or use default
const config = ref<FilterConfig>(props.taskDefinition.task_runner_config?.filter_config || defaultConfig);

// Check if a condition is a nested group
function isNestedGroup(condition: FilterCondition | FilterConditionGroup): boolean {
	return condition.operator !== undefined && Array.isArray(condition.conditions);
}

// Add a new simple condition
function addCondition() {
	config.value.conditions.push({
		field: "text_content",
		operator: "contains",
		value: "",
		case_sensitive: false,
		type: "condition" // Add the type property required by the backend
	});
	updateTaskDefinition();
}

// Add a new condition group
function addConditionGroup() {
	config.value.conditions.push({
		operator: "AND",
		conditions: [],
		type: "condition_group" // Add the type property required by the backend
	});
	updateTaskDefinition();
}

// Update a condition at a specific index
function updateCondition(index: number, updatedCondition: FilterCondition | FilterConditionGroup) {
	config.value.conditions[index] = updatedCondition;
	updateTaskDefinition();
}

// Remove a condition from the top level
function removeCondition(index: number) {
	config.value.conditions.splice(index, 1);
	updateTaskDefinition();
}

// Update the task definition with the current config
function updateTaskDefinition() {
	// Save the updated configuration to the server
	dxTaskDefinition.getAction("update").trigger(props.taskDefinition, {
		task_runner_config: { filter_config: config.value }
	});
}
</script>
