<template>
	<ActionTableLayout
		title=""
		:controller="PromptSchemaController"
		table-class="bg-slate-600"
		filter-class="bg-slate-500"
		show-filters
		refresh-button
		:filters="filters"
		:actions="getActions()"
		:columns="columns"
		:panels="panels"
	>
		<template #action-toolbar>
			<QBtn class="bg-green-900 mr-4 px-4" @click="createAction.trigger()">Create</QBtn>
		</template>
	</ActionTableLayout>
</template>
<script setup lang="ts">
import { getAction, getActions } from "@/components/Modules/Prompts/Schemas/promptSchemaActions";
import { columns } from "@/components/Modules/Prompts/Schemas/promptSchemaColumns";
import { PromptSchemaController } from "@/components/Modules/Prompts/Schemas/promptSchemaControls";
import { filters } from "@/components/Modules/Prompts/Schemas/promptSchemaFilters";
import { panels } from "@/components/Modules/Prompts/Schemas/promptSchemaPanels";
import { ActionTableLayout } from "quasar-ui-danx";

PromptSchemaController.initialize();
const createAction = getAction("create");
</script>
