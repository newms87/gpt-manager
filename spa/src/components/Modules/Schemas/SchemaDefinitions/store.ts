import { dxSchemaDefinition } from "@/components/Modules/Schemas/SchemaDefinitions/config";
import { SchemaDefinition } from "@/types";
import { ref, shallowRef } from "vue";

const isLoadingSchemaDefinitions = ref(false);
const hasLoadedSchemaDefinitions = ref(false);
const schemaDefinitions = shallowRef([]);

async function loadSchemaDefinitions(): Promise<SchemaDefinition[]> {
	if (hasLoadedSchemaDefinitions.value) return;
	await refreshSchemaDefinitions();
	hasLoadedSchemaDefinitions.value = true;
	return schemaDefinitions.value;
}

async function refreshSchemaDefinitions(): Promise<SchemaDefinition[]> {
	if (isLoadingSchemaDefinitions.value) return;
	isLoadingSchemaDefinitions.value = true;
	schemaDefinitions.value = (await dxSchemaDefinition.routes.list()).data || [];
	isLoadingSchemaDefinitions.value = false;
	return schemaDefinitions.value;
}

async function refreshSchemaDefinition(schemaDefinition: SchemaDefinition): Promise<SchemaDefinition> {
	return await dxSchemaDefinition.routes.details(schemaDefinition);
}

export {
	isLoadingSchemaDefinitions,
	hasLoadedSchemaDefinitions,
	schemaDefinitions,
	loadSchemaDefinitions,
	refreshSchemaDefinitions,
	refreshSchemaDefinition
};
