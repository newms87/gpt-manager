<template>
	<div class="relative h-full p-6 overflow-hidden flex flex-col flex-nowrap">
		<SchemaAndFragmentSelector
			v-model="activeSchema"
			v-model:fragment="activeFragment"
			v-model:previewing="isShowingCanvas"
			can-edit-schema
			can-select-schema
			can-select-fragment
			previewable
			toggle-raw-json
			show-artifact-categories
			:class="{ 'max-h-full': isShowingCanvas }"
			@update:model-value="onSelectSchemaDefinition"
		/>

		<div v-if="activeSchema && !isShowingCanvas" class="flex-grow overflow-y-auto overflow-x-hidden">
			<TeamObjectsList :schema-definition="activeSchema" />
		</div>
	</div>
</template>
<script setup lang="ts">
import SchemaAndFragmentSelector from "@/components/Modules/SchemaEditor/SchemaAndFragmentSelector.vue";
import { dxSchemaDefinition, schemaDefinitions } from "@/components/Modules/SchemaEditor";
import TeamObjectsList from "@/components/Modules/TeamObjects/TeamObjectsList";
import type { SchemaFragment } from "@/types";
import { until } from "@vueuse/core";
import { getItem, setItem } from "quasar-ui-danx";
import { computed, onMounted, ref } from "vue";

const SCHEMA_DEFINITION_STORED_KEY = "dx-schema-definition-id";

onMounted(init);

const isShowingCanvas = ref(true);
const activeFragment = ref<SchemaFragment | null>(null);
const activeSchema = computed(() => dxSchemaDefinition.activeItem.value);

async function init() {
	const storedSchemaDefinitionId = getItem(SCHEMA_DEFINITION_STORED_KEY);

	if (storedSchemaDefinitionId) {
		await until(schemaDefinitions).toMatch(pi => pi?.length > 0);
		dxSchemaDefinition.setActiveItem(schemaDefinitions.value.find(ps => ps.id === storedSchemaDefinitionId));
	}
}

async function onSelectSchemaDefinition(schemaDefinition) {
	dxSchemaDefinition.setActiveItem(schemaDefinition);
	setItem(SCHEMA_DEFINITION_STORED_KEY, schemaDefinition?.id);
}
</script>
