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
				@update:model-value="updateTaskDefinition"
			/>
		</div>

		<!-- Conditions List -->
		<div class="conditions-list space-y-4">
			<template v-for="(condition, index) in config.conditions" :key="index">
				<!-- For Condition Groups -->
				<ConditionGroupField 
					v-if="isNestedGroup(condition)" 
					:group="condition" 
					@remove="removeCondition(index)"
					@update="updateCondition(index, $event)"
				/>
				
				<!-- For Simple Conditions -->
				<SimpleConditionField 
					v-else 
					:condition="condition" 
					@remove="removeCondition(index)"
					@update="updateCondition(index, $event)"
				/>
			</template>
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
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions";
import { TaskDefinition } from "@/types";
import { ref, watch } from "vue";
import SimpleConditionField from "./SimpleConditionField.vue";
import ConditionGroupField from "./ConditionGroupField.vue";

interface Condition {
	field: string;
	operator: string;
	value?: string;
	case_sensitive?: boolean;
	fragment_selector?: any;
}

interface ConditionGroup {
	operator: 'AND' | 'OR';
	conditions: Condition[];
}

interface FilterConfig {
	operator: 'AND' | 'OR';
	conditions: (Condition | ConditionGroup)[];
}

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

// Update a condition at a specific index
function updateCondition(index: number, updatedCondition: Condition | ConditionGroup) {
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
	if (!props.taskDefinition.task_runner_config) {
		props.taskDefinition.task_runner_config = {};
	}
	props.taskDefinition.task_runner_config.filter_config = config.value;

	// Save the updated configuration to the server
	dxTaskDefinition.getAction("update").trigger(props.taskDefinition, {
		task_runner_config: props.taskDefinition.task_runner_config
	});
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
</style>
