<template>
	<ActionTableLayout
		title="Workflow Inputs"
		:controller="dxWorkflowInput"
		table-class="bg-slate-600"
		filter-class="bg-slate-500"
		show-filters
		refresh-button
		create-button
	/>
</template>
<script setup lang="ts">
import { dxWorkflowInput } from "@/components/Modules/TaskWorkflows/WorkflowInputs/config";
import { ActionTableLayout } from "quasar-ui-danx";

dxWorkflowInput.initialize();
</script>
