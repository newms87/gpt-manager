<template>
	<ConfirmDialog
		title="Assign Agent to Job"
		confirm-text="Assign"
		@confirm="onConfirm"
		@close="$emit('close')"
	>
		<SelectField
			v-model="agentId"
			label="Agent"
			:options="WorkflowController.getFieldOptions('agents').filter(a => !job.agents.find(ja => ja.id === a.value))"
		/>
	</ConfirmDialog>
</template>
<script setup lang="ts">
import { WorkflowController } from "@/components/Workflows/workflowControls";
import { WorkflowJob } from "@/components/Workflows/workflows";
import { ConfirmDialog, FlashMessages, SelectField } from "quasar-ui-danx";
import { ref } from "vue";

const emit = defineEmits(["confirm", "close"]);
defineProps<{
	job: WorkflowJob
}>();
const agentId = ref(null);
function onConfirm() {
	if (!agentId.value) {
		return FlashMessages.error("Please select an agent");
	}
	emit("confirm", { id: agentId.value });
}
</script>
