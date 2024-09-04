<template>
	<ActionTableLayout
		title="Audit Requests"
		:controller="dxAudit"
		table-class="bg-slate-600"
		filter-class="bg-slate-500"
		show-filters
		refresh-button
	/>
</template>
<script setup lang="ts">
import { dxAudit } from "@/components/Modules/Audits/config";
import { ActionTableLayout } from "quasar-ui-danx";

dxAudit.initialize();
</script>
