<template>
	<ActionTableLayout
		title="Agents"
		:controller="dxAgent"
		table-class="bg-slate-600"
		filter-class="bg-slate-500"
		show-filters
		refresh-button
		create-button
	/>
</template>
<script setup lang="ts">
import { dxAgent } from "@/components/Modules/Agents/config";
import { ActionTableLayout } from "quasar-ui-danx";

dxAgent.initialize();
</script>
