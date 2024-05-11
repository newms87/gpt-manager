<template>
	<ActionTableLayout
		:controller="WorkflowController"
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
import { getAction, getActions } from "@/components/Modules/Workflows/workflowActions";
import { columns } from "@/components/Modules/Workflows/workflowColumns";
import { WorkflowController } from "@/components/Modules/Workflows/workflowControls";
import { filters } from "@/components/Modules/Workflows/workflowFilters";
import { panels } from "@/components/Modules/Workflows/workflowPanels";
import { ActionTableLayout } from "quasar-ui-danx";

WorkflowController.initialize();
const createAction = getAction("create");
</script>
