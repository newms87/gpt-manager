<template>
	<div class="h-full p-6 overflow-y-auto">
		<div class="flex items-stretch flex-nowrap gap-4">
			<SelectOrCreateField
				v-model:editing="isEditingSchema"
				:selected="activeSchema"
				show-edit
				:can-edit="!!activeSchema"
				:options="dxPromptSchema.pagedItems.value?.data || []"
				:loading="createSchemaAction.isApplying"
				select-by-object
				option-label="name"
				create-text=""
				class="w-1/2"
				@create="onCreate"
				@update:selected="onSelectPromptSchema"
			/>
		</div>

		<div v-if="isEditingSchema" class="mt-4">
			<JSONSchemaEditor
				:prompt-schema="activeSchema"
				:model-value="activeSchema.schema as JsonSchema"
				:saved-at="activeSchema.updated_at"
				:saving="updateSchemaAction.isApplying"
				@update:model-value="updateSchemaAction.trigger(activeSchema, { schema: $event })"
			/>
		</div>

		<TeamObjectsList v-if="activeSchema && !isEditingSchema" :prompt-schema="activeSchema" />
	</div>
</template>
<script setup lang="ts">
import { dxPromptSchema } from "@/components/Modules/Prompts/Schemas";
import JSONSchemaEditor from "@/components/Modules/SchemaEditor/JSONSchemaEditor";
import TeamObjectsList from "@/components/Modules/TeamObjects/TeamObjectsList";
import { JsonSchema } from "@/types";
import { getItem, SelectOrCreateField, setItem } from "quasar-ui-danx";
import { computed, onMounted, ref } from "vue";

const PROMPT_SCHEMA_STORED_KEY = "dx-prompt-schema";

onMounted(init);

const createSchemaAction = dxPromptSchema.getAction("create");
const updateSchemaAction = dxPromptSchema.getAction("update");
const isEditingSchema = ref(false);

const activeSchema = computed(() => dxPromptSchema.activeItem.value);

async function onCreate() {
	await createSchemaAction.trigger(activeSchema.value);
}

async function init() {
	dxPromptSchema.initialize();
	dxPromptSchema.setActiveItem(getItem(PROMPT_SCHEMA_STORED_KEY));
}

async function onSelectPromptSchema(promptSchema) {
	dxPromptSchema.setActiveItem(promptSchema);
	setItem(PROMPT_SCHEMA_STORED_KEY, promptSchema);
}
</script>
