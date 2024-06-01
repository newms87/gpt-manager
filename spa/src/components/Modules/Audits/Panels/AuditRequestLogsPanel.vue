<template>
	<div class="p-6">
		<div v-for="(log, index) in logItems" :key="auditRequest.id + '-' + index" class="py-1">
			{{ log }}
		</div>
	</div>
</template>
<script setup lang="ts">
import { AuditRequest } from "@/components/Modules/Audits/audit-requests";
import { computed } from "vue";

const props = defineProps<{
	auditRequest: AuditRequest,
}>();

const logItems = computed(() => props.auditRequest.logs?.split("\n") || []);
</script>
