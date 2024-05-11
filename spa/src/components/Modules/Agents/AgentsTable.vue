<template>
	<ActionTableLayout
		:controller="AgentController"
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
import { getAction, getActions } from "@/components/Modules/Agents/agentActions";
import { columns } from "@/components/Modules/Agents/agentColumns";
import { AgentController } from "@/components/Modules/Agents/agentControls";
import { filters } from "@/components/Modules/Agents/agentFilters";
import { panels } from "@/components/Modules/Agents/agentPanels";
import { ActionTableLayout } from "quasar-ui-danx";

AgentController.initialize();
const createAction = getAction("create");
</script>
