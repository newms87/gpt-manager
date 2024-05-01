<template>
	<ActionTableLayout
		:controller="AgentController"
		table-class="bg-slate-600"
		filter-class="bg-slate-500"
		show-filters
		refresh-button
		:filters="filters"
		:actions="actions"
		:columns="columns"
		:panels="panels"
	>
		<template #action-toolbar>
			<QBtn class="bg-green-900 mr-4" @click="performAction('create')">Create</QBtn>
		</template>
	</ActionTableLayout>
</template>
<script setup lang="ts">
import { actions, performAction } from "@/components/Agents/agentsActions";
import { columns } from "@/components/Agents/agentsColumns";
import { AgentController } from "@/components/Agents/agentsControls";
import { filters } from "@/components/Agents/agentsFilters";
import { panels } from "@/components/Agents/agentsPanels";
import { ActionTableLayout } from "quasar-ui-danx";

AgentController.initialize();
</script>
