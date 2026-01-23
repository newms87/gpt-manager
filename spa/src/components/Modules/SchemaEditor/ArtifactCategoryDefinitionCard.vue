<template>
	<div>
		<!-- Header with name and actions -->
		<div class="flex items-center justify-between mb-3">
			<div class="flex items-center space-x-3 flex-grow">
				<TextField
					:model-value="localCategory.name"
					label="Name"
					prepend-label
					class="flex-grow"
					input-class="bg-slate-800 text-slate-200"
					placeholder="category_name"
					@update:model-value="onUpdate('name', $event)"
				/>
				<TextField
					:model-value="localCategory.label"
					label="Label"
					prepend-label
					class="flex-grow"
					input-class="bg-slate-800 text-slate-200"
					placeholder="Display Label"
					@update:model-value="onUpdate('label', $event)"
				/>
			</div>
			<ActionButton
				type="trash"
				color="red"
				size="md"
				tooltip="Delete category"
				class="ml-3"
				@click="onDelete"
			/>
		</div>

		<!-- Fragment Selector Toggle -->
		<div class="mb-3">
			<div class="flex items-center space-x-2">
				<ShowHideButton
					v-model="isShowingSelector"
					class="bg-slate-700"
					tooltip="Select schema properties for this artifact category"
				/>
				<span class="text-sm text-slate-300">Fragment Selector</span>
				<span v-if="selectedSummary" class="text-xs text-sky-400 ml-2">{{ selectedSummary }}</span>
			</div>
			<div v-if="isShowingSelector && schemaDefinition?.schema" class="mt-2 border border-slate-600 rounded p-2 max-h-64 overflow-y-auto">
				<SchemaObject
					:model-value="schemaDefinition.schema"
					v-model:fragment-selector="localFragmentSelector"
					readonly
					selectable
					class="min-w-48"
				/>
			</div>
		</div>

		<!-- Prompt -->
		<div class="mb-3">
			<label class="block text-sm font-medium text-slate-300 mb-1">Prompt</label>
			<MarkdownEditor
				:model-value="localCategory.prompt"
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
import SchemaObject from "@/components/Modules/SchemaEditor/SchemaObject";
import { ArtifactCategoryDefinition, FragmentSelector, SchemaDefinition } from "@/types";
import { ActionButton, BooleanField, MarkdownEditor, ShowHideButton, TextField } from "quasar-ui-danx";
import { computed, nextTick, reactive, ref, watch } from "vue";
import { useFragmentSelector } from "@/components/Modules/SchemaEditor/fragmentSelector";

const props = defineProps<{
	category: ArtifactCategoryDefinition;
	schemaDefinition: SchemaDefinition;
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

// Fragment selector state
const isShowingSelector = ref(false);
const localFragmentSelector = ref<FragmentSelector | null>(localCategory.fragment_selector || null);

// Watch for external changes to the fragment selector (from prop)
let isExternalUpdate = false;
watch(() => props.category.fragment_selector, (newSelector) => {
	isExternalUpdate = true;
	localFragmentSelector.value = newSelector || null;
	nextTick(() => { isExternalUpdate = false; });
});

// Emit update when fragment selector changes (user interaction only)
watch(localFragmentSelector, (newSelector) => {
	if (isExternalUpdate) return;
	localCategory.fragment_selector = newSelector;
	onUpdate('fragment_selector', newSelector);
}, { deep: true });

// Summary of selected properties using the fragmentSelector composable
const selectedSummary = computed(() => {
	if (!localFragmentSelector.value) return "";
	const { selectedPropertyCount, selectedObjectCount } = useFragmentSelector(
		ref(localFragmentSelector.value) as any,
		props.schemaDefinition?.schema || null
	);
	const props_count = selectedPropertyCount.value;
	const obj_count = selectedObjectCount.value;
	if (props_count === 0 && obj_count === 0) return "";
	const parts: string[] = [];
	if (obj_count > 0) parts.push(`${obj_count} object${obj_count > 1 ? "s" : ""}`);
	if (props_count > 0) parts.push(`${props_count} prop${props_count > 1 ? "s" : ""}`);
	return parts.join(", ");
});

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
