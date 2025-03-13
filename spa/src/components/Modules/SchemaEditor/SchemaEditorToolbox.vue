<template>
	<div class="flex flex-col flex-nowrap w-full" :class="{'h-full': isEditingSchema}">
		<div
			v-if="isEditingFragment || isEditingSchema || previewable || isPreviewing"
			class="flex-grow overflow-hidden"
		>
			<JSONSchemaEditor
				:dialog="dialog"
				:fragment-selector="activeFragment?.fragment_selector"
				:readonly="!activeSchema || !isEditingSchema"
				:hide-content="!isPreviewing && !isEditingSchema && !isEditingFragment"
				:hide-actions="!activeSchema"
				:schema-definition="activeSchema"
				:loading="loading"
				:model-value="activeSchema?.schema as JsonSchema"
				:saved-at="activeSchema?.updated_at"
				:saving="updateSchemaAction.isApplying"
				:selectable="isEditingFragment"
				:hide-save-state="hideSaveState"
				@update:model-value="schema => activeSchema && updateSchemaAction.trigger(activeSchema, { schema })"
				@update:fragment-selector="fragment_selector => activeFragment && updateFragmentAction.trigger(activeFragment, { fragment_selector })"
				@close="onCloseDialog"
			>
				<template #header="{isShowingRaw}">
					<div class="flex-grow flex items-center flex-nowrap space-x-4">
						<slot name="header-start" />

						<template v-if="!hideDefaultHeader">
							<ShowHideButton
								v-if="previewable"
								v-model="isPreviewing"
								:disable="!activeSchema"
								:class="buttonColor"
								tooltip="Preview Selection"
							/>
							<SelectionMenuField
								v-if="canSelect"
								v-model:editing="isEditingSchema"
								v-model:selected="activeSchema"
								selectable
								editable
								creatable
								:clearable="clearable"
								deletable
								name-editable
								:select-icon="SchemaIcon"
								label-class="text-slate-300"
								:class="{'mr-4': clearable, 'mr-8': !clearable}"
								:select-class="buttonColor"
								:options="allowedSchemaDefinitions"
								:loading="createSchemaAction.isApplying"
								@create="onCreate"
								@update="input => activeSchema && updateSchemaAction.trigger(activeSchema, input)"
								@delete="selected => deleteSchemaAction.trigger(selected)"
							/>

							<template v-if="activeSchema && canSelectFragment">
								<SelectionMenuField
									v-if="canSelect"
									v-model:editing="isEditingFragment"
									v-model:selected="activeFragment"
									selectable
									editable
									creatable
									clearable
									deletable
									name-editable
									:select-icon="FragmentIcon"
									label-class="text-slate-300"
									:select-class="buttonColor"
									:options="fragmentList"
									:loading="createFragmentAction.isApplying"
									@create="onCreateFragment"
									@update="input => activeFragment && updateFragmentAction.trigger(activeFragment, input)"
									@delete="selected => deleteFragmentAction.trigger(selected)"
								>
									<template #no-selection>
										<div class="text-green-700 flex items-center flex-nowrap text-no-wrap">
											<FullSchemaIcon class="w-4 mr-2" />
											Full schema
										</div>
									</template>
								</SelectionMenuField>

								<SelectField
									v-if="isShowingRaw"
									class="ml-4"
									select-class="dx-select-field-dense"
									:model-value="activeSchema.schema_format"
									:options="schemaFormatOptions"
									@update:model-value="schema_format => updateSchemaAction.trigger(activeSchema, {schema_format})"
								/>
							</template>
						</template>
					</div>
				</template>
				<template #actions>
					<ShowHideButton
						v-if="example"
						v-model="isShowingExample"
						class="bg-amber-700"
						tooltip="Show Example Response"
					/>
				</template>
			</JSONSchemaEditor>

			<SchemaResponseExampleCard v-if="isShowingExample" :schema-definition="activeSchema" />
		</div>
	</div>
</template>
<script setup lang="ts">
import JSONSchemaEditor from "@/components/Modules/SchemaEditor/JSONSchemaEditor";
import SchemaResponseExampleCard from "@/components/Modules/SchemaEditor/SchemaResponseExampleCard";
import { dxSchemaDefinition } from "@/components/Modules/Schemas/SchemaDefinitions";
import { loadSchemaDefinitions, schemaDefinitions } from "@/components/Modules/Schemas/SchemaDefinitions/store";
import { dxSchemaFragment } from "@/components/Modules/Schemas/SchemaFragments";
import { routes } from "@/components/Modules/Schemas/SchemaFragments/config/routes";
import { JsonSchema, SchemaDefinition, SchemaFragment } from "@/types";
import {
	FaSolidCircleCheck as FullSchemaIcon,
	FaSolidDatabase as SchemaIcon,
	FaSolidPuzzlePiece as FragmentIcon
} from "danx-icon";
import { FlashMessages, SelectField, SelectionMenuField, ShowHideButton, storeObjects } from "quasar-ui-danx";
import { computed, onMounted, ref, shallowRef, watch } from "vue";

const instanceId = Math.random().toString(36).substring(7);

const props = withDefaults(defineProps<{
	canSelect?: boolean;
	canSelectFragment?: boolean;
	previewable?: boolean;
	clearable?: boolean;
	example?: boolean;
	loading?: boolean;
	buttonColor?: string;
	excludeSchemaIds?: string[] | number[];
	dialog?: boolean;
	hideDefaultHeader?: boolean;
	hideSaveState?: boolean;
}>(), {
	buttonColor: "bg-sky-800",
	excludeSchemaIds: null
});

const createSchemaAction = dxSchemaDefinition.getAction("create");
const updateSchemaAction = dxSchemaDefinition.getAction("update");
const deleteSchemaAction = dxSchemaDefinition.getAction("delete");
const createFragmentAction = dxSchemaFragment.getAction("quick-create");
const updateFragmentAction = dxSchemaFragment.getAction("update");
const deleteFragmentAction = dxSchemaFragment.getAction("delete", { onFinish: loadFragments });
const activeSchema = defineModel<SchemaDefinition>();
const activeFragment = defineModel<SchemaFragment>("fragment");
const isEditingSchema = defineModel<boolean>("editing");
const isEditingFragment = defineModel<boolean>("selectingFragment");
const isPreviewing = defineModel<boolean>("previewing");
const isShowingExample = ref(false);

const schemaFormatOptions = [
	{ label: "JSON", value: "json" },
	{ label: "YAML", value: "yaml" },
	{ label: "Typescript", value: "ts" }
];

const allowedSchemaDefinitions = computed(() => schemaDefinitions.value.filter(s => !props.excludeSchemaIds?.includes(s.id)));

// Load fragments when the active schema changes
const fragmentList = shallowRef([]);
onMounted(() => {
	loadSchemaDefinitions();
	loadFragments();
});
watch(() => activeSchema.value, loadFragments);

// Create a new schema
async function onCreate() {
	const response = await createSchemaAction.trigger();

	if (!response.result) {
		return FlashMessages.error("Failed to create schema: " + response.error || "There was a problem communicating with the server");
	}

	activeSchema.value = response.result;
}

// Create a new fragment
async function onCreateFragment() {
	const response = await createFragmentAction.trigger(null, { schema_definition_id: activeSchema.value.id });

	if (!response.result || !response.item) {
		return FlashMessages.error("Failed to create fragment: " + response.error || "There was a problem communicating with the server");
	}

	activeFragment.value = response.item;
	fragmentList.value.push(response.item);
}

// Load the fragments for the current active schema
async function loadFragments() {
	if (!activeSchema.value) return;

	// NOTE The use of abortOn is to avoid generating duplicate requests at the same time causing this request to abort, leaving this instance w/o any fragments
	const fragments = await routes.list({ filter: { schema_definition_id: activeSchema.value.id } }, { abortOn: instanceId });
	fragmentList.value = storeObjects(fragments.data);
}

function onCloseDialog() {
	isPreviewing.value = false;
	isEditingSchema.value = false;
	isEditingFragment.value = false;
}
</script>
