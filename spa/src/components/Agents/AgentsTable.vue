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
import { actions, performAction } from "@/components/Agents/agentActions";
import { columns } from "@/components/Agents/agentColumns";
import { AgentController } from "@/components/Agents/agentControls";
import { filters } from "@/components/Agents/agentFilters";
import { panels } from "@/components/Agents/agentPanels";
import { ActionTableLayout } from "quasar-ui-danx";

AgentController.initialize();
</script>
