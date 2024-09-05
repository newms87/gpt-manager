<template>
	<ConfirmDialog
		title="Select Workflow Input"
		:confirm-text="selectedInput ? 'Select ' + selectedInput.name : 'Select an input...'"
		content-class="w-[80vw]"
		:disabled="!selectedInput"
		@confirm="$emit('confirm', selectedInput)"
		@close="$emit('close')"
	>
		<ActionTableLayout
			title="Workflow Inputs"
			:controller="dxWorkflowInput"
			table-class="bg-slate-600"
			filter-class="bg-slate-500"
			show-filters
			create-button
			selection="single"
		/>
	</ConfirmDialog>
</template>
<script setup lang="ts">
import { dxWorkflowInput } from "@/components/Modules/Workflows/WorkflowInputs";
import { WorkflowInput } from "@/types";
import { ActionTableLayout, ConfirmDialog } from "quasar-ui-danx";
import { computed, onMounted } from "vue";

defineEmits(["confirm", "close"]);
defineProps<{
	filter?: Partial<WorkflowInput>;
}>();

// Modify the 'create' action behavior so we reload the list and select the created item
dxWorkflowInput.modifyAction("create", {
	onFinish: ({ item }) => {
		if (item) {
			dxWorkflowInput.loadList();
			dxWorkflowInput.setSelectedRows([item]);
		}
	}
});
dxWorkflowInput.columns = dxWorkflowInput.columns.filter(col => ["id", "name", "description", "tags", "created_at"].includes(col.name));
const selectedInput = computed(() => dxWorkflowInput.selectedRows.value[0] || null);
onMounted(() => {
	dxWorkflowInput.initialize();
	dxWorkflowInput.setPagination({ rowsPerPage: 10 });
});
</script>
