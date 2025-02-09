<template>
	<div class="relative h-full p-6 overflow-hidden flex flex-col flex-nowrap">
		<SchemaEditorToolbox
			v-model:editing="isEditingSchema"
			:model-value="activeSchema"
			can-select
			@update:model-value="onSelectPromptSchema"
		/>

		<div class="flex-grow overflow-y-auto overflow-x-hidden">
			<TeamObjectsList v-if="activeSchema && !isEditingSchema" :prompt-schema="activeSchema" />
		</div>
	</div>
</template>
<script setup lang="ts">
import SchemaEditorToolbox from "@/components/Modules/SchemaEditor/SchemaEditorToolbox";
import { dxPromptSchema } from "@/components/Modules/Schemas/Schemas";
import TeamObjectsList from "@/components/Modules/TeamObjects/TeamObjectsList";
import { until } from "@vueuse/core";
import { getItem, setItem } from "quasar-ui-danx";
import { computed, onMounted, ref } from "vue";

const PROMPT_SCHEMA_STORED_KEY = "dx-prompt-schema-id";

onMounted(init);

const isEditingSchema = ref(false);
const activeSchema = computed(() => dxPromptSchema.activeItem.value);

async function init() {
	dxPromptSchema.initialize();
	const storedPromptSchemaId = getItem(PROMPT_SCHEMA_STORED_KEY);

	if (storedPromptSchemaId) {
		await until(dxPromptSchema.pagedItems).toMatch(pi => pi?.data.length > 0);
		dxPromptSchema.setActiveItem(dxPromptSchema.pagedItems.value.data.find(ps => ps.id === storedPromptSchemaId));
	}
}

async function onSelectPromptSchema(promptSchema) {
	dxPromptSchema.setActiveItem(promptSchema);
	setItem(PROMPT_SCHEMA_STORED_KEY, promptSchema?.id);
}
</script>
