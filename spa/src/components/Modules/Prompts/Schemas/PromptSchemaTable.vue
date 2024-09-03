<template>
	<ActionTableLayout
		title=""
		:controller="dxPromptSchema"
		table-class="bg-slate-600"
		filter-class="bg-slate-500"
		show-filters
		refresh-button
		:filters="filters"
		:columns="columns"
		:panels="panels"
	>
		<template #action-toolbar>
			<QBtn class="bg-green-900 mr-4 px-4" @click="createAction.trigger()">Create</QBtn>
		</template>
	</ActionTableLayout>
</template>
<script setup lang="ts">
import { dxPromptSchema } from "@/components/Modules/Prompts/Schemas";
import { columns } from "@/components/Modules/Prompts/Schemas/config/columns";
import { filters } from "@/components/Modules/Prompts/Schemas/config/filters";
import { panels } from "@/components/Modules/Prompts/Schemas/config/panels";
import { ActionTableLayout } from "quasar-ui-danx";

dxPromptSchema.initialize();
const createAction = dxPromptSchema.getAction("create");
</script>
