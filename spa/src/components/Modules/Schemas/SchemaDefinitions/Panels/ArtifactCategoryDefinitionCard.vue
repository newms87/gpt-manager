<template>
	<div>
		<!-- Header with name and actions -->
		<div class="flex items-center justify-between mb-3">
			<div class="flex items-center space-x-3 flex-grow">
				<TextField
					v-model="localCategory.name"
					label="Name"
					class="flex-grow"
					input-class="bg-slate-800 text-slate-200"
					placeholder="category_name"
					@update:model-value="onUpdate('name', $event)"
				/>
				<TextField
					v-model="localCategory.label"
					label="Label"
					class="flex-grow"
					input-class="bg-slate-800 text-slate-200"
					placeholder="Display Label"
					@update:model-value="onUpdate('label', $event)"
				/>
			</div>
			<ActionButton
				type="trash"
				color="red"
				size="sm"
				tooltip="Delete category"
				class="ml-3"
				@click="onDelete"
			/>
		</div>

		<!-- Fragment Selector -->
		<div class="mb-3">
			<SelectField
				v-model="selectedRelationship"
				label="Target Relationship"
				:options="relationshipOptions"
				class="w-full"
				select-class="bg-slate-800 text-slate-200"
				@update:model-value="onRelationshipChange"
			/>
			<div class="text-xs text-slate-400 mt-1">
				Select which relationship in the schema this artifact category applies to, or leave as root for the main TeamObject.
			</div>
		</div>

		<!-- Prompt -->
		<div class="mb-3">
			<label class="block text-sm font-medium text-slate-300 mb-1">Prompt</label>
			<QInput
				v-model="localCategory.prompt"
				type="textarea"
				:rows="3"
				class="bg-slate-800 rounded"
				input-class="text-slate-200"
				placeholder="Describe how the AI should generate this artifact..."
				@update:model-value="onUpdate('prompt', $event)"
			/>
		</div>

		<!-- Toggles -->
		<div class="flex items-center space-x-6">
			<BooleanField
				v-model="localCategory.editable"
				label="Editable"
				@update:model-value="onUpdate('editable', $event)"
			/>
			<BooleanField
				v-model="localCategory.deletable"
				label="Deletable"
				@update:model-value="onUpdate('deletable', $event)"
			/>
		</div>
	</div>
</template>

<script setup lang="ts">
import { ArtifactCategoryDefinition, SchemaDefinition } from "@/types";
import { ActionButton, BooleanField, SelectField, TextField } from "quasar-ui-danx";
import { computed, reactive, watch } from "vue";

const props = defineProps<{
	category: ArtifactCategoryDefinition;
	schemaDefinition: SchemaDefinition;
	relationshipOptions: { label: string; value: string[] | null }[];
}>();

const emit = defineEmits<{
	update: [category: ArtifactCategoryDefinition, data: Partial<ArtifactCategoryDefinition>];
	delete: [category: ArtifactCategoryDefinition];
}>();

// Local reactive copy for editing
const localCategory = reactive<ArtifactCategoryDefinition>({ ...props.category });

// Watch for external changes to the category
watch(() => props.category, (newCategory) => {
	Object.assign(localCategory, newCategory);
}, { deep: true });

/**
 * Computed value for the selected relationship dropdown
 */
const selectedRelationship = computed({
	get: () => {
		// Find the option that matches the current fragment_selector
		const selector = localCategory.fragment_selector;
		if (!selector) return null;
		return props.relationshipOptions.find(opt =>
			JSON.stringify(opt.value) === JSON.stringify(selector)
		)?.value ?? null;
	},
	set: (value: string[] | null) => {
		localCategory.fragment_selector = value;
	}
});

/**
 * Handle relationship selection change
 */
function onRelationshipChange(value: string[] | null) {
	localCategory.fragment_selector = value;
	emit("update", props.category, { fragment_selector: value });
}

/**
 * Debounced update handler
 */
let updateTimeout: ReturnType<typeof setTimeout> | null = null;
function onUpdate(field: keyof ArtifactCategoryDefinition, value: unknown) {
	if (updateTimeout) clearTimeout(updateTimeout);
	updateTimeout = setTimeout(() => {
		emit("update", props.category, { [field]: value });
	}, 500);
}

/**
 * Handle delete
 */
function onDelete() {
	emit("delete", props.category);
}
</script>
