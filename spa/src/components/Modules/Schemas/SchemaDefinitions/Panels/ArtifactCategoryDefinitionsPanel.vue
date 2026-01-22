<template>
	<div class="p-6 h-full overflow-y-auto">
		<!-- Create button -->
		<div class="mb-4">
			<ActionButton
				type="create"
				label="Add Category"
				color="sky-invert"
				:loading="isCreating"
				@click="createCategory"
			/>
		</div>

		<!-- Empty state -->
		<div
			v-if="categories.length === 0 && !isLoading"
			class="bg-slate-700 rounded-lg p-6 text-center text-slate-400"
		>
			<CategoryIcon class="w-8 h-8 mx-auto mb-3 opacity-50" />
			<p class="font-medium">No artifact categories defined</p>
			<p class="text-sm mt-1">Add categories to define how artifacts are organized for this schema.</p>
		</div>

		<!-- Loading state -->
		<div v-if="isLoading" class="flex items-center justify-center py-8">
			<QSpinner color="sky" size="md" />
		</div>

		<!-- Categories list -->
		<ListTransition v-else>
			<div
				v-for="category in categories"
				:key="category.id"
				class="bg-slate-700 rounded-lg p-4 mb-3"
			>
				<ArtifactCategoryDefinitionCard
					:category="category"
					:schema-definition="schemaDefinition"
					:relationship-options="relationshipOptions"
					@update="updateCategory"
					@delete="deleteCategory"
				/>
			</div>
		</ListTransition>
	</div>
</template>

<script setup lang="ts">
import { apiUrls } from "@/api";
import ArtifactCategoryDefinitionCard from "@/components/Modules/Schemas/SchemaDefinitions/Panels/ArtifactCategoryDefinitionCard.vue";
import { ArtifactCategoryDefinition, JsonSchema, SchemaDefinition } from "@/types";
import { FaSolidLayerGroup as CategoryIcon } from "danx-icon";
import { ActionButton, FlashMessages, ListTransition, request } from "quasar-ui-danx";
import { computed, onMounted, ref } from "vue";

const props = defineProps<{
	schemaDefinition: SchemaDefinition;
}>();

const categories = ref<ArtifactCategoryDefinition[]>([]);
const isLoading = ref(false);
const isCreating = ref(false);

/**
 * Extract relationship paths from the schema
 * Returns an array of options like [{ label: "providers", value: ["providers"] }]
 */
const relationshipOptions = computed(() => {
	const options: { label: string; value: string[] | null }[] = [
		{ label: "(Root - TeamObject)", value: null }
	];

	const schema = props.schemaDefinition?.schema;
	if (!schema?.properties) return options;

	// Find array properties (relationships) in the schema
	function findRelationships(obj: JsonSchema, path: string[] = []) {
		if (!obj?.properties) return;

		for (const [key, value] of Object.entries(obj.properties)) {
			const currentPath = [...path, key];
			if (value.type === "array") {
				options.push({
					label: currentPath.join(" > "),
					value: currentPath
				});
				// Recurse into array items if they have properties
				if (value.items?.properties) {
					findRelationships(value.items, currentPath);
				}
			} else if (value.type === "object" && value.properties) {
				findRelationships(value, currentPath);
			}
		}
	}

	findRelationships(schema);
	return options;
});

/**
 * Load artifact category definitions for this schema
 */
async function loadCategories() {
	isLoading.value = true;
	try {
		const response = await request.get(apiUrls.schemas.artifactCategoryDefinitions, {
			filter: { schema_definition_id: props.schemaDefinition.id }
		});
		categories.value = response.data || [];
	} catch (e) {
		FlashMessages.error("Failed to load artifact categories");
	} finally {
		isLoading.value = false;
	}
}

/**
 * Create a new artifact category definition
 */
async function createCategory() {
	isCreating.value = true;
	try {
		const response = await request.post(apiUrls.schemas.artifactCategoryDefinitions, {
			schema_definition_id: props.schemaDefinition.id,
			name: "new_category",
			label: "New Category",
			prompt: "Describe how to generate this artifact...",
			editable: true,
			deletable: true
		});
		if (response.item) {
			categories.value.push(response.item);
		}
	} catch (e) {
		FlashMessages.error("Failed to create artifact category");
	} finally {
		isCreating.value = false;
	}
}

/**
 * Update an artifact category definition
 */
async function updateCategory(category: ArtifactCategoryDefinition, data: Partial<ArtifactCategoryDefinition>) {
	try {
		await request.patch(`${apiUrls.schemas.artifactCategoryDefinitions}/${category.id}`, data);
		// Update local state
		const index = categories.value.findIndex(c => c.id === category.id);
		if (index !== -1) {
			categories.value[index] = { ...categories.value[index], ...data };
		}
	} catch (e) {
		FlashMessages.error("Failed to update artifact category");
	}
}

/**
 * Delete an artifact category definition
 */
async function deleteCategory(category: ArtifactCategoryDefinition) {
	try {
		await request.delete(`${apiUrls.schemas.artifactCategoryDefinitions}/${category.id}`);
		categories.value = categories.value.filter(c => c.id !== category.id);
	} catch (e) {
		FlashMessages.error("Failed to delete artifact category");
	}
}

onMounted(loadCategories);
</script>
