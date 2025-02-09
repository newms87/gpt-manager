<template>
	<ActionTableLayout
		title=""
		:controller="dxPromptSchema"
		table-class="bg-slate-600"
		filter-class="bg-slate-500"
		show-filters
		refresh-button
		create-button
	/>
</template>
<script setup lang="ts">
import { dxPromptSchema } from "@/components/Modules/Prompts/SchemaDefinitions";
import { ActionTableLayout } from "quasar-ui-danx";

dxPromptSchema.initialize();
</script>
