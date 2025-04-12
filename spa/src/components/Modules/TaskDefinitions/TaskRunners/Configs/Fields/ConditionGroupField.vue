<template>
	<div class="condition-card border-4 border-sky-800 overflow-hidden rounded-lg">
		<div class="flex-x bg-sky-800 text-sky-200 p-4">
			<div class="flex-grow text-lg font-bold">Condition Group</div>
			<ActionButton
				type="trash"
				color="sky"
				size="sm"
				@click="$emit('remove')"
			/>
		</div>

		<div class="p-4">
			<AndOrConditionTabs
				v-if="localGroup.conditions?.length > 1"
				v-model="localGroup.operator"
				class="mb-4"
				@update:model-value="emitUpdate"
			/>

			<!-- Recursive Rendering of Nested Conditions -->
			<ListTransition class="nested-conditions space-y-4">
				<div v-for="(condition, index) in localGroup.conditions" :key="index">
					<SimpleConditionField
						:model-value="condition"
						@update:model-value="updateCondition(index, $event)"
						@remove="removeCondition(index)"
					/>
				</div>

				<!-- Add Nested Condition Button -->
				<ActionButton
					type="create"
					color="blue"
					label="Add Condition"
					class="mt-2"
					@click="addCondition"
				/>
			</ListTransition>
		</div>
	</div>
</template>

<script setup lang="ts">
import AndOrConditionTabs from "@/components/Modules/TaskDefinitions/TaskRunners/Configs/Fields/AndOrConditionTabs";
import { FilterCondition, FilterConditionGroup } from "@/types";
import { ActionButton, ListTransition } from "quasar-ui-danx";
import { computed } from "vue";
import SimpleConditionField from "./SimpleConditionField.vue";

const props = defineProps<{
	group: FilterConditionGroup;
}>();

const emit = defineEmits<{
	remove: [];
	update: [FilterConditionGroup];
}>();

// Use computed property to handle two-way binding
const localGroup = computed({
	get: () => props.group,
	set: (val) => emit("update", val)
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
function updateCondition(index: number, updatedCondition: FilterCondition) {
	localGroup.value.conditions[index] = updatedCondition;
	emitUpdate();
}

// Emit update event when any value changes
function emitUpdate() {
	emit("update", localGroup.value);
}
</script>
