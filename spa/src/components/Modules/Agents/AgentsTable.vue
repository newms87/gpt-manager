<template>
	<ActionTableLayout
		title="Agents"
		:controller="dxAgent"
		table-class="bg-slate-600"
		filter-class="bg-slate-500"
		show-filters
		refresh-button
		:filters="filters"
		:actions="dxAgent.getActions()"
		:columns="columns"
		:panels="panels"
	>
		<template #action-toolbar>
			<QBtn class="bg-green-900 mr-4 px-4" @click="createAction.trigger()">Create</QBtn>
		</template>
	</ActionTableLayout>
</template>
<script setup lang="ts">
import { dxAgent } from "@/components/Modules/Agents/config";
import { columns } from "@/components/Modules/Agents/config/columns";
import { filters } from "@/components/Modules/Agents/config/filters";
import { panels } from "@/components/Modules/Agents/config/panels";
import { ActionTableLayout } from "quasar-ui-danx";

dxAgent.initialize();
const createAction = dxAgent.getAction("create");
</script>
