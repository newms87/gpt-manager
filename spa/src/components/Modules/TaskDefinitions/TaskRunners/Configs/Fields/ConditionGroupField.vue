<template>
	<div class="condition-card p-4 border rounded-lg bg-gray-50">
		<div class="flex justify-between items-center mb-2">
			<div class="text-md font-medium">Condition Group</div>
			<div class="flex">
				<QBtn
					icon="delete"
					color="negative"
					flat
					round
					dense
					@click="$emit('remove')"
				/>
			</div>
		</div>

		<div class="mb-2">
			<QBtnToggle
				v-model="localGroup.operator"
				:options="[
					{ label: 'AND', value: 'AND' },
					{ label: 'OR', value: 'OR' },
				]"
				class="w-full"
				spread
				@update:model-value="emitUpdate"
			/>
		</div>

		<!-- Recursive Rendering of Nested Conditions -->
		<div class="nested-conditions pl-4 border-l-2 border-blue-200">
			<div v-for="(condition, index) in localGroup.conditions" :key="index" class="mb-2">
				<SimpleConditionField 
					:condition="condition" 
					@remove="removeCondition(index)" 
					@update="updateCondition(index, $event)"
				/>
			</div>

			<!-- Add Nested Condition Button -->
			<QBtn
				color="primary"
				label="Add Condition"
				icon="add"
				flat
				class="mt-2"
				@click="addCondition"
			/>
		</div>
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import SimpleConditionField from './SimpleConditionField.vue';

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

const props = defineProps<{
	group: ConditionGroup;
}>();

const emit = defineEmits<{
	remove: [];
	update: [ConditionGroup];
}>();

// Use computed property to handle two-way binding
const localGroup = computed({
	get: () => props.group,
	set: (val) => emit('update', val)
});

// Add a new condition to the group
function addCondition() {
	localGroup.value.conditions.push({
		field: "text_content",
		operator: "contains",
		value: "",
		case_sensitive: false
	});
	emitUpdate();
}

// Remove a condition from the group
function removeCondition(index: number) {
	localGroup.value.conditions.splice(index, 1);
	emitUpdate();
}

// Update a specific condition in the group
function updateCondition(index: number, updatedCondition: Condition) {
	localGroup.value.conditions[index] = updatedCondition;
	emitUpdate();
}

// Emit update event when any value changes
function emitUpdate() {
	emit('update', localGroup.value);
}
</script>
