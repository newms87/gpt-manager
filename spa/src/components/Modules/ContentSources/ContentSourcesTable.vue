<template>
	<ActionTableLayout
		title="Content Sources"
		:controller="dxContentSource"
		table-class="bg-slate-600"
		filter-class="bg-slate-500"
		show-filters
		refresh-button
		create-button
	/>
</template>
<script setup lang="ts">
import { dxContentSource } from "@/components/Modules/ContentSources/config";
import { ActionTableLayout } from "quasar-ui-danx";

dxContentSource.initialize();
</script>
