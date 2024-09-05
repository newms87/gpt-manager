<template>
	<ActionTableLayout
		title="Workflows"
		:controller="dxWorkflow"
		table-class="bg-slate-600"
		filter-class="bg-slate-500"
		show-filters
		refresh-button
		create-button
	>
		<template #action-toolbar>
			<QBtn class="bg-green-900 mr-4 px-4" @click="dxWorkflow.action('import-json')">
				<ImportIcon class="w-4 mr-2" />
				Import
			</QBtn>
		</template>
	</ActionTableLayout>
</template>
<script setup lang="ts">
import { dxWorkflow } from "@/components/Modules/Workflows/config";
import { FaSolidFileImport as ImportIcon } from "danx-icon";
import { ActionTableLayout } from "quasar-ui-danx";

dxWorkflow.initialize();
</script>
