<template>
	<div class="flex flex-col flex-nowrap" :class="{'h-full': isEditingSchema}">
		<div
			v-if="isEditingFragment || isEditingSchema || previewable || isPreviewing"
			class="flex-grow overflow-hidden"
		>
			<JSONSchemaEditor
				:fragment-selector="activeFragment?.fragment_selector"
				:readonly="!activeSchema || !isEditingSchema"
				:hide-content="!isPreviewing && !isEditingSchema && !isEditingFragment"
				:hide-actions="!activeSchema"
				:prompt-schema="activeSchema"
				:loading="loading"
				:model-value="activeSchema?.schema as JsonSchema"
				:saved-at="activeSchema?.updated_at"
				:saving="updateSchemaAction.isApplying"
				:selectable="isEditingFragment"
				@update:model-value="schema => activeSchema && updateSchemaAction.trigger(activeSchema, { schema })"
				@update:fragment-selector="fragment_selector => activeFragment && updateFragmentAction.trigger(activeFragment, { fragment_selector })"
			>
				<template #header="{isShowingRaw}">
					<div class="flex-grow flex items-center flex-nowrap">
						<SelectionMenuField
							v-if="canSelect"
							v-model:editing="isEditingSchema"
							v-model:selected="activeSchema"
							selectable
							editable
							creatable
							clearable
							deletable
							name-editable
							:select-icon="SchemaIcon"
							label-class="text-slate-300"
							:select-class="buttonColor"
							:options="dxPromptSchema.pagedItems.value?.data || []"
							:loading="createSchemaAction.isApplying"
							@create="onCreate"
							@update="input => activeSchema && updateSchemaAction.trigger(activeSchema, input)"
							@delete="selected => deleteSchemaAction.trigger(selected)"
						/>

						<template v-if="activeSchema">
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
									<div class="text-green-700 flex items-center flex-nowrap">
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
					</div>
				</template>
				<template #actions>
					<ShowHideButton v-if="previewable" v-model="isPreviewing" :class="buttonColor" tooltip="Preview Selection" />
					<ShowHideButton
						v-if="example"
						v-model="isShowingExample"
						class="bg-amber-700"
						tooltip="Show Example Response"
					/>
				</template>
			</JSONSchemaEditor>

			<SchemaResponseExampleCard v-if="isShowingExample" :prompt-schema="activeSchema" />
		</div>
	</div>
</template>
<script setup lang="ts">
import { dxPromptSchemaFragment } from "@/components/Modules/Prompts/SchemaFragments";
import { routes } from "@/components/Modules/Prompts/SchemaFragments/config/routes";
import { dxPromptSchema } from "@/components/Modules/Prompts/Schemas";
import JSONSchemaEditor from "@/components/Modules/SchemaEditor/JSONSchemaEditor";
import SchemaResponseExampleCard from "@/components/Modules/SchemaEditor/SchemaResponseExampleCard";
import { JsonSchema, PromptSchema, PromptSchemaFragment } from "@/types";
import {
	FaSolidCircleCheck as FullSchemaIcon,
	FaSolidDatabase as SchemaIcon,
	FaSolidPuzzlePiece as FragmentIcon
} from "danx-icon";
import { FlashMessages, SelectField, SelectionMenuField, ShowHideButton, storeObjects } from "quasar-ui-danx";
import { onMounted, ref, shallowRef, watch } from "vue";

withDefaults(defineProps<{
	canSelect?: boolean;
	canSelectFragment?: boolean;
	previewable?: boolean;
	example?: boolean;
	loading?: boolean;
	buttonColor?: string;
}>(), {
	buttonColor: "bg-sky-800"
});

onMounted(() => dxPromptSchema.initialize());
const createSchemaAction = dxPromptSchema.getAction("create");
const updateSchemaAction = dxPromptSchema.getAction("update");
const deleteSchemaAction = dxPromptSchema.getAction("delete");
const createFragmentAction = dxPromptSchemaFragment.getAction("quick-create");
const updateFragmentAction = dxPromptSchemaFragment.getAction("update");
const deleteFragmentAction = dxPromptSchemaFragment.getAction("delete", { onFinish: loadFragments });
const activeSchema = defineModel<PromptSchema>();
const activeFragment = defineModel<PromptSchemaFragment>("fragment");
const isEditingSchema = defineModel<boolean>("editing");
const isEditingFragment = defineModel<boolean>("selectingFragment");
const isPreviewing = defineModel<boolean>("previewing");
const isShowingExample = ref(false);

const schemaFormatOptions = [
	{ label: "JSON", value: "json" },
	{ label: "YAML", value: "yaml" },
	{ label: "Typescript", value: "ts" }
];

// Load fragments when the active schema changes
const fragmentList = shallowRef([]);
onMounted(loadFragments);
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
	const response = await createFragmentAction.trigger(null, { prompt_schema_id: activeSchema.value.id });

	if (!response.result || !response.item) {
		return FlashMessages.error("Failed to create fragment: " + response.error || "There was a problem communicating with the server");
	}

	activeFragment.value = response.item;
	fragmentList.value.push(response.item);
}

// Load the fragments for the current active schema
async function loadFragments() {
	if (!activeSchema.value) return;

	const fragments = await routes.list({ filter: { prompt_schema_id: activeSchema.value.id } });
	fragmentList.value = storeObjects(fragments.data);
}
</script>
