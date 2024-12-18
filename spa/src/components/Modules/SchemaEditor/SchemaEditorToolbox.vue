<template>
	<div class="flex flex-col flex-nowrap" :class="{'h-full': isEditingSchema}">
		<SelectOrCreateField
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

		<div class="flex-grow pt-4 h-full">
			<JSONSchemaEditor
				v-if="isEditingSchema"
				:prompt-schema="activeSchema"
				:model-value="activeSchema.schema as JsonSchema"
				:saved-at="activeSchema.updated_at"
				:saving="updateSchemaAction.isApplying"
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
			</JSONSchemaEditor>
		</div>
	</div>
</template>
<script setup lang="ts">
import { dxPromptSchema } from "@/components/Modules/Prompts/Schemas";
import JSONSchemaEditor from "@/components/Modules/SchemaEditor/JSONSchemaEditor";
import { JsonSchema, PromptSchema } from "@/types";
import { EditableDiv, FlashMessages, SelectField, SelectOrCreateField } from "quasar-ui-danx";
import { onMounted } from "vue";

onMounted(() => dxPromptSchema.initialize());
const createSchemaAction = dxPromptSchema.getAction("create");
const updateSchemaAction = dxPromptSchema.getAction("update");
const activeSchema = defineModel<PromptSchema>();
const isEditingSchema = defineModel<boolean>("editing");

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
