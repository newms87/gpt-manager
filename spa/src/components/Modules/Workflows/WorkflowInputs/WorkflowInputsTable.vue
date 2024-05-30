<template>
	<ActionTableLayout
		title="Workflow Inputs"
		:controller="WorkflowInputController"
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
import { getAction, getActions } from "@/components/Modules/Workflows/WorkflowInputs/workflowInputActions";
import { columns } from "@/components/Modules/Workflows/WorkflowInputs/workflowInputColumns";
import { WorkflowInputController } from "@/components/Modules/Workflows/WorkflowInputs/workflowInputControls";
import { filters } from "@/components/Modules/Workflows/WorkflowInputs/workflowInputFilters";
import { panels } from "@/components/Modules/Workflows/WorkflowInputs/workflowInputPanels";
import { ActionTableLayout } from "quasar-ui-danx";
import { useRouter } from "vue-router";

WorkflowInputController.initialize({ vueRouter: useRouter() });
const createAction = getAction("create");
</script>
