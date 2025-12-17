<template>
	<div class="p-6">
		<template v-if="auditRequest?.audits?.length === 0">
			<div class="text-xl text-center text-gray-500">No Audits</div>
		</template>
		<template v-else>
			<div v-for="audit in auditRequest.audits" :key="audit.id" class="mb-6">
				<div class="bg-slate-800 text-slate-400 p-2 rounded text-lg">{{ audit.event }} {{
						audit.auditable_title
					}}
				</div>
				<CodeViewer
					class="md-bg-red"
					:model-value="audit.old_values"
					format="json"
				/>
				<CodeViewer
					class="md-bg-green"
					:model-value="audit.new_values"
					format="json"
				/>
			</div>
		</template>
	</div>
</template>
<script setup lang="ts">
import { AuditRequest } from "@/components/Modules/Audits/audit-requests";
import { CodeViewer } from "quasar-ui-danx";

defineProps<{
	auditRequest: AuditRequest,
}>();
</script>
