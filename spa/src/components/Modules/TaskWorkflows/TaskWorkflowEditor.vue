<template>
	<ActionTableLayout
		title="Task Definitions"
		:controller="dxTaskDefinition"
		table-class="bg-slate-600"
		filter-class="bg-slate-500"
		show-filters
		refresh-button
		create-button
	/>
</template>
<script setup lang="ts">
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions/config";
import { ActionTableLayout } from "quasar-ui-danx";

dxTaskDefinition.initialize();
</script>
