<template>
	<div class="flex flex-col flex-nowrap" :class="{'h-full': isEditingSchema}">
		<div class="flex items-center flex-nowrap mb-4">
			<SelectOrCreateField
				v-if="canSelect"
				v-model:editing="isEditingSchema"
				:selected="activeSchema"
				show-edit
				:can-edit="!!activeSchema"
				:options="dxPromptSchema.pagedItems.value?.data || []"
				:loading="createSchemaAction.isApplying"
				select-by-object
				option-label="name"
				class="w-1/2"
				@create="onCreate"
				@update:selected="selected => activeSchema = selected as PromptSchema"
			/>
			<ShowHideButton
				v-model="isSelectingSchema"
				class="bg-sky-800 !p-3 ml-4"
				:show-icon="AllSelectedIcon"
				:label="isSelectingSchema ? 'Done' : 'Edit Selection'"
			/>
		</div>
		<div class="flex-grow h-full">
			<JSONSchemaEditor
				:readonly="!isEditingSchema"
				:hide-content="isPreviewingExample"
				:prompt-schema="activeSchema"
				:model-value="activeSchema.schema as JsonSchema"
				:saved-at="activeSchema.updated_at"
				:saving="updateSchemaAction.isApplying"
				:selectable="isSelectingSchema"
				@update:model-value="schema => updateSchemaAction.trigger(activeSchema, { schema })"
			>
				<template #header="{isShowingRaw}">
					<div class="flex-grow flex items-center flex-nowrap">
						<EditableDiv
							color="slate-600"
							:model-value="activeSchema.name"
							@update:model-value="name => updateSchemaAction.trigger(activeSchema, {name})"
						/>
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
import { JsonSchema, PromptSchema } from "@/types";
import { FaSolidListCheck as AllSelectedIcon } from "danx-icon";
import { EditableDiv, FlashMessages, SelectField, SelectOrCreateField, ShowHideButton } from "quasar-ui-danx";
import { onMounted, ref } from "vue";

defineProps<{ canSelect?: boolean }>();

onMounted(() => dxPromptSchema.initialize());
const createSchemaAction = dxPromptSchema.getAction("create");
const updateSchemaAction = dxPromptSchema.getAction("update");
const activeSchema = defineModel<PromptSchema>();
const isEditingSchema = defineModel<boolean>("editing");
const isSelectingSchema = defineModel<boolean>("selecting");
const isPreviewingExample = ref(false);

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
