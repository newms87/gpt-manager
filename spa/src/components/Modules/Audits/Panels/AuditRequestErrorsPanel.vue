<template>
	<div class="p-6">
		<template v-if="auditRequest?.errors?.length === 0">
			<div class="text-xl text-center text-gray-500">No Errors</div>
		</template>
		<template v-else>
			<div v-for="error in auditRequest.errors" :key="error.id" class="mb-6">
				{{ error }}
			</div>
		</template>
	</div>
</template>
<script setup lang="ts">
import { AuditRequest } from "@/components/Modules/Audits/audit-requests";

defineProps<{
	auditRequest: AuditRequest,
}>();
</script>
