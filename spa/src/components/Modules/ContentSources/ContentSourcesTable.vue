<template>
	<ActionTableLayout
		title="Content Sources"
		:controller="ContentSourceController"
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
import { getAction, getActions } from "@/components/Modules/ContentSources/contentSourceActions";
import { columns } from "@/components/Modules/ContentSources/contentSourceColumns";
import { ContentSourceController } from "@/components/Modules/ContentSources/contentSourceControls";
import { filters } from "@/components/Modules/ContentSources/contentSourceFilters";
import { panels } from "@/components/Modules/ContentSources/contentSourcePanels";
import { ActionTableLayout } from "quasar-ui-danx";
import { useRouter } from "vue-router";

ContentSourceController.initialize({ vueRouter: useRouter() });
const createAction = getAction("create");
</script>
