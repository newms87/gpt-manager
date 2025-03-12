<template>
	<div class="relative h-full p-6 overflow-hidden flex flex-col flex-nowrap">
		<SchemaEditorToolbox
			v-model:editing="isEditingSchema"
			class="max-h-full"
			:model-value="activeSchema"
			can-select
			previewable
			@update:model-value="onSelectSchemaDefinition"
		/>

		<div v-if="activeSchema && !isEditingSchema" class="flex-grow overflow-y-auto overflow-x-hidden">
			<TeamObjectsList :schema-definition="activeSchema" />
		</div>
	</div>
</template>
<script setup lang="ts">
import SchemaEditorToolbox from "@/components/Modules/SchemaEditor/SchemaEditorToolbox";
import { dxSchemaDefinition } from "@/components/Modules/Schemas/SchemaDefinitions";
import TeamObjectsList from "@/components/Modules/TeamObjects/TeamObjectsList";
import { until } from "@vueuse/core";
import { getItem, setItem } from "quasar-ui-danx";
import { computed, onMounted, ref } from "vue";

const SCHEMA_DEFINITION_STORED_KEY = "dx-schema-definition-id";

onMounted(init);

const isEditingSchema = ref(false);
const activeSchema = computed(() => dxSchemaDefinition.activeItem.value);

async function init() {
	dxSchemaDefinition.initialize();
	const storedSchemaDefinitionId = getItem(SCHEMA_DEFINITION_STORED_KEY);

	if (storedSchemaDefinitionId) {
		await until(dxSchemaDefinition.pagedItems).toMatch(pi => pi?.data.length > 0);
		dxSchemaDefinition.setActiveItem(dxSchemaDefinition.pagedItems.value.data.find(ps => ps.id === storedSchemaDefinitionId));
	}
}

async function onSelectSchemaDefinition(schemaDefinition) {
	dxSchemaDefinition.setActiveItem(schemaDefinition);
	setItem(SCHEMA_DEFINITION_STORED_KEY, schemaDefinition?.id);
}
</script>
