<template>
	<ActionTableLayout
		title=""
		:controller="PromptDirectiveController"
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
import { getAction, getActions } from "@/components/Modules/Prompts/Directives/promptDirectiveActions";
import { columns } from "@/components/Modules/Prompts/Directives/promptDirectiveColumns";
import { PromptDirectiveController } from "@/components/Modules/Prompts/Directives/promptDirectiveControls";
import { filters } from "@/components/Modules/Prompts/Directives/promptDirectiveFilters";
import { panels } from "@/components/Modules/Prompts/Directives/promptDirectivePanels";
import { ActionTableLayout } from "quasar-ui-danx";

PromptDirectiveController.initialize();
const createAction = getAction("create");
</script>
