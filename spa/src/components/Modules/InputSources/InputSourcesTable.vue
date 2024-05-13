<template>
	<ActionTableLayout
		title="Input Sources"
		:controller="InputSourceController"
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
import { getAction, getActions } from "@/components/Modules/InputSources/inputSourceActions";
import { columns } from "@/components/Modules/InputSources/inputSourceColumns";
import { InputSourceController } from "@/components/Modules/InputSources/inputSourceControls";
import { filters } from "@/components/Modules/InputSources/inputSourceFilters";
import { panels } from "@/components/Modules/InputSources/inputSourcePanels";
import { ActionTableLayout } from "quasar-ui-danx";

InputSourceController.initialize();
const createAction = getAction("create");
</script>
