<template>
	<ActionTableLayout
		title=""
		:controller="dxSchemaDefinition"
		table-class="bg-slate-600"
		filter-class="bg-slate-500"
		show-filters
		refresh-button
		create-button
	/>
</template>
<script setup lang="ts">
import { dxSchemaDefinition } from "@/components/Modules/Schemas/SchemaDefinitions";
import { ActionTableLayout } from "quasar-ui-danx";

dxSchemaDefinition.initialize();
</script>
