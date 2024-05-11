<template>
	<ConfirmDialog
		title="Assign Agent to Job"
		confirm-text="Assign"
		@confirm="onConfirm"
		@close="$emit('close')"
	>
		<SelectField
			v-model="agentIds"
			label="Agent"
			multiple
			:options="availableAgents"
		/>
	</ConfirmDialog>
</template>
<script setup lang="ts">
import { WorkflowController } from "@/components/Modules/Workflows/workflowControls";
import { WorkflowJob } from "@/components/Modules/Workflows/workflows";
import { ConfirmDialog, FlashMessages, SelectField } from "quasar-ui-danx";
import { computed, ref } from "vue";

const emit = defineEmits(["confirm", "close"]);
const props = defineProps<{
	job: WorkflowJob
}>();
const agentIds = ref([]);
function onConfirm() {
	if (agentIds.value.length === 0) {
		return FlashMessages.error("Please select at least 1 agent to assign.");
	}
	emit("confirm", { ids: agentIds.value });
}

const availableAgents = computed(() => WorkflowController.getFieldOptions("agents").filter(a => !props.job.assignments.find(ja => ja.agent.id === a.value)));
</script>
