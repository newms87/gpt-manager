import { dxSchemaDefinition } from "@/components/Modules/SchemaEditor/config";
import { SchemaDefinition } from "@/types";
import { until } from "@vueuse/core";
import { ListControlsPagination } from "quasar-ui-danx";
import { ref, shallowRef } from "vue";

const isLoadingSchemaDefinitions = ref(false);
const hasLoadedSchemaDefinitions = ref(false);
const schemaDefinitions = shallowRef([]);

async function loadSchemaDefinitions(pager: ListControlsPagination = null): Promise<SchemaDefinition[]> {
	if (hasLoadedSchemaDefinitions.value) return;
	await refreshSchemaDefinitions(pager);
	hasLoadedSchemaDefinitions.value = true;
	return schemaDefinitions.value;
}

async function refreshSchemaDefinitions(pager: ListControlsPagination = null): Promise<SchemaDefinition[]> {
	if (isLoadingSchemaDefinitions.value) {
		// If we are currently loading the schema definitions, wait until the schema definitions list has changed, then return the new schema definitions list
		await until(schemaDefinitions).changed();
		return schemaDefinitions.value;
	}
	isLoadingSchemaDefinitions.value = true;
	schemaDefinitions.value = (await dxSchemaDefinition.routes.list(pager)).data || [];
	isLoadingSchemaDefinitions.value = false;
	return schemaDefinitions.value;
}

async function refreshSchemaDefinition(schemaDefinition: SchemaDefinition, fields?: object): Promise<SchemaDefinition> {
	return await dxSchemaDefinition.routes.details(schemaDefinition, fields);
}

export {
	isLoadingSchemaDefinitions,
	hasLoadedSchemaDefinitions,
	schemaDefinitions,
	loadSchemaDefinitions,
	refreshSchemaDefinitions,
	refreshSchemaDefinition
};
