<template>
	<div class="flex flex-col flex-nowrap" :class="{'h-full': isEditingSchema}">
		<div
			v-if="activeSchema && (isSelectingFragment || isEditingSchema || showPreview)"
			class="flex-grow overflow-hidden"
		>
			<JSONSchemaEditor
				v-model:sub-selection="subSelection"
				:readonly="!isEditingSchema"
				:hide-content="isPreviewingExample"
				:prompt-schema="activeSchema"
				:model-value="activeSchema.schema as JsonSchema"
				:saved-at="activeSchema.updated_at"
				:saving="updateSchemaAction.isApplying"
				:selectable="isSelectingFragment"
				@update:model-value="schema => updateSchemaAction.trigger(activeSchema, { schema })"
			>
				<template #header="{isShowingRaw}">
					<div class="flex-grow flex items-center flex-nowrap">
						<SelectionMenuField
							v-if="canSelect"
							v-model:editing="isEditingSchema"
							:selected="activeSchema"
							selectable
							editable
							creatable
							clearable
							deletable
							name-editable
							:select-icon="SchemaIcon"
							label-class="text-slate-300"
							:can-edit="!!activeSchema"
							:options="dxPromptSchema.pagedItems.value?.data || []"
							:loading="createSchemaAction.isApplying"
							@create="onCreate"
							@update="input => updateSchemaAction.trigger(activeSchema, input)"
							@delete="selected => deleteSchemaAction.trigger(selected)"
							@update:selected="selected => console.log('selected', selected) || (activeSchema = selected as PromptSchema)"
						/>

						<ShowHideButton
							v-model="isSelectingFragment"
							class="bg-sky-800 mr-2 ml-4"
							tooltip="Select Schema Fragment"
							:show-icon="FragmentIcon"
						/>
						<EditableDiv
							v-if="activeFragment"
							color="slate-600"
							:model-value="activeFragment.name"
							@update:model-value="name => updateFragmentAction.trigger(activeFragment, {name})"
						/>
						<div v-else class="text-green-700 flex items-center flex-nowrap">
							<FullSchemaIcon class="w-4 mr-2" />
							Full schema
						</div>
						<SelectField
							v-if="isShowingRaw"
							class="ml-4"
							select-class="dx-select-field-dense"
							:model-value="activeSchema.schema_format"
							:options="schemaFormatOptions"
							@update:model-value="schema_format => updateSchemaAction.trigger(activeSchema, {schema_format})"
						/>
					</div>
				</template>
				<template #actions>
					<ShowHideButton
						v-model="isPreviewingExample"
						class="bg-amber-700"
						tooltip="Preview Example Response"
					/>
				</template>
			</JSONSchemaEditor>

			<SchemaResponseExampleCard v-if="isPreviewingExample" :prompt-schema="activeSchema" />
		</div>
	</div>
</template>
<script setup lang="ts">
import { dxPromptSchema } from "@/components/Modules/Prompts/Schemas";
import JSONSchemaEditor from "@/components/Modules/SchemaEditor/JSONSchemaEditor";
import SchemaResponseExampleCard from "@/components/Modules/SchemaEditor/SchemaResponseExampleCard";
import { useSubSelection } from "@/components/Modules/SchemaEditor/subSelection";
import { JsonSchema, PromptSchema, PromptSchemaFragment, SelectionSchema } from "@/types";
import {
	FaSolidCircleCheck as FullSchemaIcon,
	FaSolidDatabase as SchemaIcon,
	FaSolidPuzzlePiece as FragmentIcon
} from "danx-icon";
import { EditableDiv, FlashMessages, SelectField, SelectionMenuField, ShowHideButton } from "quasar-ui-danx";
import { onMounted, ref } from "vue";

defineProps<{ canSelect?: boolean, canSelectFragment?: boolean, showPreview?: boolean }>();

onMounted(() => dxPromptSchema.initialize());
const createSchemaAction = dxPromptSchema.getAction("create");
const updateSchemaAction = dxPromptSchema.getAction("update");
const deleteSchemaAction = dxPromptSchema.getAction("delete");
const updateFragmentAction = dxPromptSchema.getAction("update");
const activeSchema = defineModel<PromptSchema>();
const activeFragment = defineModel<PromptSchemaFragment>("activeFragment");
const isEditingSchema = defineModel<boolean>("editing");
const isSelectingSchema = defineModel<boolean>("selectingSchema");
const isSelectingFragment = defineModel<boolean>("selectingFragment");
const subSelection = defineModel<SelectionSchema | null>("subSelection");
const isPreviewingExample = ref(false);

const { selectedObjectCount, selectedPropertyCount } = useSubSelection(subSelection, activeSchema.value?.schema);

const schemaFormatOptions = [
	{ label: "JSON", value: "json" },
	{ label: "YAML", value: "yaml" },
	{ label: "Typescript", value: "ts" }
];

async function onCreate() {
	const response = await createSchemaAction.trigger();

	if (!response.result) {
		return FlashMessages.error("Failed to create schema: " + response.error || "There was a problem communicating with the server");
	}

	activeSchema.value = response.result;
}
</script>
