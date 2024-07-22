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
			:controller="WorkflowInputController"
			table-class="bg-slate-600"
			filter-class="bg-slate-500"
			show-filters
			selection="single"
			:filters="filters"
			:columns="dialogColumns"
		>
			<template #action-toolbar>
				<QBtn class="bg-green-900 px-4" @click="createAction.trigger()">Create</QBtn>
			</template>
		</ActionTableLayout>
	</ConfirmDialog>
</template>
<script setup lang="ts">
import { getAction } from "@/components/Modules/Workflows/WorkflowInputs/workflowInputActions";
import { columns } from "@/components/Modules/Workflows/WorkflowInputs/workflowInputColumns";
import { WorkflowInputController } from "@/components/Modules/Workflows/WorkflowInputs/workflowInputControls";
import { filters } from "@/components/Modules/Workflows/WorkflowInputs/workflowInputFilters";
import { WorkflowInput } from "@/types";
import { ActionTableLayout, ConfirmDialog } from "quasar-ui-danx";
import { computed, onMounted } from "vue";

defineEmits(["confirm", "close"]);
const props = defineProps<{
	filter?: Partial<WorkflowInput>;
}>();

const createAction = getAction("create", {
	onFinish: ({ item }) => {
		if (item) {
			WorkflowInputController.loadList();
			console.log("finished", item);
			WorkflowInputController.setSelectedRows([item]);
		}
	}
});
const allowedColumns = ["id", "name", "description", "tags", "created_at"];
const dialogColumns = computed(() => columns.filter(c => allowedColumns.includes(c.name)).map(c => ({
	...c,
	onClick: undefined
})));
const selectedInput = computed(() => WorkflowInputController.selectedRows.value[0] || null);
onMounted(() => {
	WorkflowInputController.initialize();
});
</script>
