<template>
	<ActionTableLayout
		title=""
		:controller="dxPromptDirective"
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
import { dxPromptDirective } from "@/components/Modules/Prompts/Directives/config";
import { columns } from "@/components/Modules/Prompts/Directives/config/columns";
import { filters } from "@/components/Modules/Prompts/Directives/config/filters";
import { panels } from "@/components/Modules/Prompts/Directives/config/panels";
import { ActionTableLayout } from "quasar-ui-danx";

dxPromptDirective.initialize();
const createAction = dxPromptDirective.getAction("create");
</script>
