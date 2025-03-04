<template>
	<PanelsDrawer
		:title="schemaDefinition.name"
		:panels="dxSchemaDefinition.panels"
		:target="schemaDefinition"
		position="standard"
		panels-class="w-full"
		@close="$emit('close')"
	/>
</template>

<script lang="ts" setup>
import { dxSchemaDefinition } from "@/components/Modules/Schemas/SchemaDefinitions";
import { SchemaDefinition } from "@/types";
import { PanelsDrawer } from "quasar-ui-danx";

export interface SchemaDefinitionPanelsDialogProps {
	schemaDefinition: SchemaDefinition;
}

defineEmits(["close"]);
const props = defineProps<SchemaDefinitionPanelsDialogProps>();
dxSchemaDefinition.routes.details(props.schemaDefinition);
dxSchemaDefinition.loadFieldOptions();
</script>
