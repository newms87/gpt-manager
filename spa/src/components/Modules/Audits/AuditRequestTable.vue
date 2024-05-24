<template>
	<ActionTableLayout
		title="Audit Requests"
		:controller="AuditRequestController"
		table-class="bg-slate-600"
		filter-class="bg-slate-500"
		show-filters
		refresh-button
		:filters="filters"
		:columns="columns"
		:panels="panels"
	/>
</template>
<script setup lang="ts">
import { columns } from "@/components/Modules/Audits/auditRequestColumns";
import { AuditRequestController } from "@/components/Modules/Audits/auditRequestControls";
import { filters } from "@/components/Modules/Audits/auditRequestFilters";
import { panels } from "@/components/Modules/Audits/auditRequestPanels";
import { ActionTableLayout } from "quasar-ui-danx";
import { useRouter } from "vue-router";

AuditRequestController.initialize({ vueRouter: useRouter() });
</script>
