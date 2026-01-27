<template>
	<div class="schema-and-fragment-selector flex flex-col w-full" :class="{ 'h-full min-h-[400px]': showCanvas }">
		<!-- Header with selection dropdowns -->
		<div class="flex-x flex-shrink-0 space-x-4 p-2 bg-slate-800">
			<slot name="header-start" />

			<template v-if="!hideDefaultHeader">
				<!-- Preview toggle -->
				<ShowHideButton
					v-if="previewable"
					v-model="previewing"
					:disabled="!schema || !canView"
					:class="buttonColor"
					:tooltip="canView ? 'Show/Hide Schema Editor' : 'Preview disabled: You do not have permission to view this schema.'"
				/>

				<!-- Schema selection -->
				<SelectionMenuField
					v-if="canSelectSchema"
					v-model:selected="schema"
					selectable
					creatable
					:clearable="clearable"
					deletable
					name-editable
					:edit-disabled="!canEdit"
					select-text="Schema"
					:select-icon="SchemaIcon"
					label-class="text-slate-300"
					:placeholder="placeholder"
					:class="{'mr-4': clearable, 'mr-8': !clearable}"
					:select-class="buttonColor"
					:options="allowedSchemaDefinitions"
					:loading="createSchemaAction.isApplying"
					@create="onCreateSchema"
					@update="input => schema && updateSchemaAction.trigger(schema, input)"
					@delete="selected => deleteSchemaAction.trigger(selected)"
				/>

				<!-- Fragment selection -->
				<template v-if="schema && canSelectFragment">
					<SelectionMenuField
						v-model:selected="fragment"
						selectable
						creatable
						clearable
						deletable
						name-editable
						:edit-disabled="!canEdit"
						:select-icon="FragmentIcon"
						label-class="text-slate-300"
						select-text="Fragment"
						:select-class="buttonColor"
						:options="fragmentList"
						:loading="createFragmentAction.isApplying"
						@create="onCreateFragment"
						@update="input => fragment && updateFragmentAction.trigger(fragment, input)"
						@delete="selected => deleteFragmentAction.trigger(selected)"
					>
						<template #no-selection>
							<div class="text-green-700 flex-x text-no-wrap">
								<FullSchemaIcon class="w-4 mr-2" />
								Full schema
							</div>
						</template>
					</SelectionMenuField>
				</template>
			</template>

			<slot name="header-end" />

			<slot name="actions" />
		</div>

		<!-- Save state indicator -->
		<SaveStateIndicator
			v-if="!hideSaveState && schema"
			class="flex-shrink-0"
			:saving="updateSchemaAction.isApplying || updateFragmentAction.isApplying"
			:saved-at="schema?.updated_at"
		/>

		<!-- Visual editor canvas - only show when previewing OR when in dialog mode -->
		<div v-if="showCanvas" class="flex-1 overflow-hidden">
			<template v-if="dialog">
				<!-- Dialog mode: only show dialog when editing or selecting -->
				<FragmentSelectorDialog
					v-model:showing="isDialogShowing"
					:schema="schema.schema"
					:model-value="fragment?.fragment_selector ?? null"
					:selection-mode="selectionMode"
					@update:model-value="onUpdateFragmentSelector"
				/>
			</template>

			<template v-else>
				<!-- Inline mode: show canvas directly -->
				<FragmentSelectorCanvas
					v-model:artifacts-visible="artifactsVisible"
					:schema="schema.schema"
					:model-value="fragment?.fragment_selector ?? null"
					:selection-enabled="isSelectionEnabled"
					:edit-enabled="isEditEnabled"
					:selection-mode="selectionMode"
					:artifacts-enabled="showArtifactCategories"
					:artifact-category-definitions="artifactCategoryDefinitions"
					:adding-artifact-path="addingArtifactPath"
					@update:model-value="onUpdateFragmentSelector"
					@update:schema="onUpdateSchema"
					@add-artifact="onAddArtifact"
					@update-artifact="onUpdateArtifact"
					@delete-artifact="onDeleteArtifact"
				/>
			</template>
		</div>

		<!-- Empty state when no schema - only show if no schema selected -->
		<div v-else-if="!schema?.schema" class="flex-1 flex items-center justify-center text-slate-500">
			<template v-if="canSelectSchema">
				Select a schema to begin
			</template>
			<template v-else>
				No schema available
			</template>
		</div>
	</div>
</template>

<script setup lang="ts">
import { apiUrls } from "@/api";
import { dxSchemaDefinition } from "@/components/Modules/SchemaEditor/config";
import FragmentSelectorCanvas from "@/components/Modules/SchemaEditor/FragmentSelector/FragmentSelectorCanvas.vue";
import FragmentSelectorDialog from "@/components/Modules/SchemaEditor/FragmentSelector/FragmentSelectorDialog.vue";
import { SelectionMode } from "@/components/Modules/SchemaEditor/FragmentSelector/types";
import { loadSchemaDefinitions, refreshSchemaDefinition, refreshSchemaDefinitions, schemaDefinitions } from "@/components/Modules/SchemaEditor/store";
import { dxSchemaFragment } from "@/components/Modules/Schemas/SchemaFragments";
import { routes as fragmentRoutes } from "@/components/Modules/Schemas/SchemaFragments/config/routes";
import { ArtifactCategoryDefinition, FragmentSelector, JsonSchema, SchemaDefinition, SchemaFragment } from "@/types";
import {
	FaSolidCircleCheck as FullSchemaIcon,
	FaSolidDatabase as SchemaIcon,
	FaSolidPuzzlePiece as FragmentIcon
} from "danx-icon";
import { useLocalStorage } from "@vueuse/core";
import { FlashMessages, request, SaveStateIndicator, SelectionMenuField, ShowHideButton } from "quasar-ui-danx";
import { computed, onMounted, ref, shallowRef, watch } from "vue";

const instanceId = Math.random().toString(36).substring(7);

const props = withDefaults(defineProps<{
	// Selection capabilities
	canSelectSchema?: boolean;
	canSelectFragment?: boolean;
	canEditSchema?: boolean;
	canEditFragment?: boolean;

	// Display options
	dialog?: boolean;
	previewable?: boolean;
	clearable?: boolean;
	buttonColor?: string;
	placeholder?: string;
	hideDefaultHeader?: boolean;
	hideSaveState?: boolean;

	// Artifact options
	showArtifactCategories?: boolean;

	// Filtering and mode
	excludeSchemaIds?: (string | number)[];
	selectionMode?: SelectionMode;
}>(), {
	canSelectSchema: true,
	canSelectFragment: true,
	canEditSchema: false,
	canEditFragment: false,
	dialog: false,
	previewable: false,
	clearable: false,
	buttonColor: "bg-sky-800",
	placeholder: "(Select Schema)",
	hideDefaultHeader: false,
	hideSaveState: false,
	showArtifactCategories: false,
	excludeSchemaIds: () => [],
	selectionMode: "by-property"
});

// Models for two-way binding
const schema = defineModel<SchemaDefinition | null>();
const fragment = defineModel<SchemaFragment | null>("fragment");
const editing = defineModel<boolean>("editing");
const selectingFragment = defineModel<boolean>("selectingFragment");
const previewing = defineModel<boolean>("previewing");

// Artifact visibility toggle state (persisted in localStorage)
const artifactsVisible = useLocalStorage("schema-editor-artifacts-visible", false);

// Track which model path is currently adding an artifact (for loading state)
const addingArtifactPath = ref<string | null>(null);

// Computed permission states
const canView = computed(() => schema.value?.can?.view !== false);
const canEdit = computed(() => schema.value?.can?.edit !== false);

// Artifact category definitions from schema
const artifactCategoryDefinitions = computed(() =>
	schema.value?.artifact_category_definitions ?? []
);

// Whether artifacts exist
const hasArtifacts = computed(() => artifactCategoryDefinitions.value.length > 0);

// Artifact count for badge
const artifactCount = computed(() => artifactCategoryDefinitions.value.length);

// Computed: show canvas when schema exists and either previewing or in dialog mode
const showCanvas = computed(() => schema.value?.schema && (previewing.value || props.dialog));

// Computed: is selection enabled on canvas (only when a fragment is selected)
const isSelectionEnabled = computed(() => {
	return !!fragment.value;
});

// Computed: is edit enabled on canvas (always enabled when canEditSchema is true)
const isEditEnabled = computed(() => {
	return props.canEditSchema;
});

// Dialog state for dialog mode
const isDialogShowing = computed({
	get: () => editing.value || selectingFragment.value,
	set: (value: boolean) => {
		if (!value) {
			editing.value = false;
			selectingFragment.value = false;
		}
	}
});

// Schema actions
const createSchemaAction = dxSchemaDefinition.getAction("create-with-name", { onFinish: refreshSchemaDefinitions });
const updateSchemaAction = dxSchemaDefinition.getAction("update");
const deleteSchemaAction = dxSchemaDefinition.getAction("delete-with-confirm", {
	onFinish: async () => {
		await refreshSchemaDefinitions();
		if (!schemaDefinitions.value.find(s => s.id === schema.value?.id)) {
			schema.value = null;
		}
	}
});

// Fragment actions
const createFragmentAction = dxSchemaFragment.getAction("create", { onFinish: loadFragments });
const updateFragmentAction = dxSchemaFragment.getAction("update");
const deleteFragmentAction = dxSchemaFragment.getAction("delete-with-confirm", { onFinish: loadFragments });

// Filtered schema definitions based on excludeSchemaIds
const allowedSchemaDefinitions = computed(() =>
	schemaDefinitions.value.filter(s => !props.excludeSchemaIds?.includes(s.id))
);

// Fragment list for the current schema
const fragmentList = shallowRef<SchemaFragment[]>([]);

// Load schema definitions on mount
onMounted(() => {
	loadSchemaDefinitions();
	loadFragments();
});

// Reload fragments when schema changes
watch(() => schema.value, loadFragments);

// Load artifact_category_definitions when schema changes and showArtifactCategories is enabled
watch(() => schema.value, async (newSchema) => {
	if (props.showArtifactCategories && newSchema && !newSchema.artifact_category_definitions) {
		await refreshSchemaDefinition(newSchema, { artifact_category_definitions: true });
	}
}, { immediate: true });

// Create a new schema
async function onCreateSchema(): Promise<void> {
	const response = await createSchemaAction.trigger();

	if (!response.result) {
		FlashMessages.error("Failed to create schema: " + (response.error || "There was a problem communicating with the server"));
		return;
	}

	schema.value = response.result;
}

// Create a new fragment
async function onCreateFragment(): Promise<void> {
	if (!schema.value) return;

	const response = await createFragmentAction.trigger(null, { schema_definition_id: schema.value.id });

	if (!response.result || !response.item) {
		FlashMessages.error("Failed to create fragment: " + (response.error || "There was a problem communicating with the server"));
		return;
	}

	fragment.value = response.item;
	if (!fragmentList.value.find(f => f.id === response.item.id)) {
		fragmentList.value = [...fragmentList.value, response.item];
	}
}

// Load fragments for the current schema
async function loadFragments(): Promise<void> {
	if (!schema.value) {
		fragmentList.value = [];
		return;
	}

	// Use instanceId as requestKey to avoid duplicate request cancellation issues
	const response = await fragmentRoutes.list(
		{ filter: { schema_definition_id: schema.value.id } },
		{ requestKey: instanceId }
	);
	fragmentList.value = response.data;
}

// Handle fragment selector updates from the canvas
async function onUpdateFragmentSelector(fragmentSelector: object | null): Promise<void> {
	if (!fragment.value) return;
	await updateFragmentAction.trigger(fragment.value, { fragment_selector: fragmentSelector });
}

// Handle schema updates from the canvas (edit mode)
async function onUpdateSchema(updatedSchema: JsonSchema): Promise<void> {
	if (!schema.value) return;
	await updateSchemaAction.trigger(schema.value, { schema: updatedSchema });
}

// Artifact event handlers

/**
 * Build a FragmentSelector from a model path (e.g., "root.users.address")
 */
function buildFragmentSelectorFromPath(path: string, schemaObj: JsonSchema): FragmentSelector | null {
	if (path === "root") return null; // null means targets root

	const parts = path.split(".").slice(1); // Remove "root" prefix
	if (parts.length === 0) return null;

	// Build nested fragment selector by navigating schema to get types
	let schemaPointer = schemaObj;
	const result: FragmentSelector = { type: "object" };
	let pointer = result;

	for (let i = 0; i < parts.length; i++) {
		const part = parts[i];
		const props = schemaPointer.properties || schemaPointer.items?.properties;
		const childSchema = props?.[part];

		if (!childSchema) break;

		const childType = (childSchema.type || "object") as "object" | "array";

		if (i === parts.length - 1) {
			// Last part - just include the type
			pointer.children = { [part]: { type: childType } };
		} else {
			// Intermediate part - continue nesting
			pointer.children = { [part]: { type: childType } };
			pointer = pointer.children[part];
			schemaPointer = childSchema.items || childSchema;
		}
	}

	return result;
}

/**
 * Create a new artifact category definition targeting the specified model path
 */
async function onAddArtifact(payload: { modelPath: string }): Promise<void> {
	if (!schema.value) return;

	addingArtifactPath.value = payload.modelPath;

	try {
		// Build fragment_selector from modelPath
		const fragmentSelector = buildFragmentSelectorFromPath(payload.modelPath, schema.value.schema);

		// Generate unique name based on existing artifacts count
		const existingCount = artifactCategoryDefinitions.value.length;
		const uniqueSuffix = existingCount > 0 ? `_${existingCount + 1}` : "";
		const name = `new_artifact${uniqueSuffix}`;

		const response = await request.post(apiUrls.schemas.artifactCategoryDefinitions + "/apply-action", {
			action: "create",
			data: {
				schema_definition_id: schema.value.id,
				name,
				label: "New Artifact",
				prompt: "Describe how to generate this artifact...",
				fragment_selector: fragmentSelector,
				editable: true,
				deletable: true
			}
		});

		if (response.item) {
			// Refresh schema to get updated artifact_category_definitions
			await refreshSchemaDefinition(schema.value, { artifact_category_definitions: true });
		}
	} catch (e) {
		FlashMessages.error("Failed to create artifact category");
	} finally {
		addingArtifactPath.value = null;
	}
}

/**
 * Update an existing artifact category definition
 */
async function onUpdateArtifact(acd: ArtifactCategoryDefinition, updates: Partial<ArtifactCategoryDefinition>): Promise<void> {
	if (!schema.value) return;

	try {
		await request.post(`${apiUrls.schemas.artifactCategoryDefinitions}/${acd.id}/apply-action`, {
			action: "update",
			data: updates
		});
		await refreshSchemaDefinition(schema.value, { artifact_category_definitions: true });
	} catch (e) {
		FlashMessages.error("Failed to update artifact category");
	}
}

/**
 * Delete an artifact category definition
 */
async function onDeleteArtifact(acd: ArtifactCategoryDefinition): Promise<void> {
	if (!schema.value) return;

	try {
		await request.post(`${apiUrls.schemas.artifactCategoryDefinitions}/${acd.id}/apply-action`, {
			action: "delete"
		});
		await refreshSchemaDefinition(schema.value, { artifact_category_definitions: true });
	} catch (e) {
		FlashMessages.error("Failed to delete artifact category");
	}
}
</script>

<style lang="scss" scoped>
// Height styling is now handled via conditional Tailwind classes on the root element
</style>
