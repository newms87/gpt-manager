<template>
	<PanelsDrawer
		:title="`Audit Request: ${auditRequest.url}`"
		:panels="fullWidthPanels"
		:target="auditRequest"
		position="standard"
		panels-class="w-full"
		@close="$emit('close')"
	/>
</template>

<script lang="ts" setup>
import { dxAudit } from "@/components/Modules/Audits/config";
import { AuditRequest } from "@/components/Modules/Audits/audit-requests";
import { PanelsDrawer } from "quasar-ui-danx";
import { computed } from "vue";

export interface AuditRequestPanelsDialogProps {
	auditRequest: AuditRequest;
}

defineEmits(["close"]);
const props = defineProps<AuditRequestPanelsDialogProps>();
dxAudit.routes.details(props.auditRequest);

// Override panel widths to use full dialog width instead of fixed 80em
const fullWidthPanels = computed(() =>
	dxAudit.panels.map(panel => ({
		...panel,
		class: "w-full"
	}))
);
</script>
