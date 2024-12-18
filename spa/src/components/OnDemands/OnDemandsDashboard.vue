<template>
	<div class="h-full p-6 overflow-hidden flex flex-col flex-nowrap">
		<SchemaEditorToolbox
			v-model:editing="isEditingSchema"
			:model-value="activeSchema"
			@update:model-value="onSelectPromptSchema"
		/>

		<div class="flex-grow overflow-y-auto overflow-x-hidden">
			<TeamObjectsList v-if="activeSchema && !isEditingSchema" :prompt-schema="activeSchema" />
		</div>
	</div>
</template>
<script setup lang="ts">
import { dxPromptSchema } from "@/components/Modules/Prompts/Schemas";
import SchemaEditorToolbox from "@/components/Modules/SchemaEditor/SchemaEditorToolbox";
import TeamObjectsList from "@/components/Modules/TeamObjects/TeamObjectsList";
import { getItem, setItem } from "quasar-ui-danx";
import { computed, onMounted, ref } from "vue";

const PROMPT_SCHEMA_STORED_KEY = "dx-prompt-schema";

onMounted(init);

const isEditingSchema = ref(false);
const activeSchema = computed(() => dxPromptSchema.activeItem.value);

async function init() {
	dxPromptSchema.initialize();
	dxPromptSchema.setActiveItem(getItem(PROMPT_SCHEMA_STORED_KEY));
}

async function onSelectPromptSchema(promptSchema) {
	dxPromptSchema.setActiveItem(promptSchema);
	setItem(PROMPT_SCHEMA_STORED_KEY, promptSchema);
}
</script>
