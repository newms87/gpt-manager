<template>
	<ActionTableLayout
		title=""
		:controller="dxPromptDirective"
		table-class="bg-slate-600"
		filter-class="bg-slate-500"
		show-filters
		refresh-button
		create-button
	/>
</template>
<script setup lang="ts">
import { dxPromptDirective } from "@/components/Modules/Prompts/Directives";
import { ActionTableLayout } from "quasar-ui-danx";

dxPromptDirective.initialize();
</script>
