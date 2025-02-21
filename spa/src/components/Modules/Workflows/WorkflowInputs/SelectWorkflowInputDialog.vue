<template>
	<InfoDialog
		:disable="loading || saving"
		title="Select Input"
		content-class="w-[50rem]"
		done-class="bg-slate-700"
		@close="$emit('close')"
	>
		<div :class="{'opacity-50': loading || saving}">
			<div>
				<TextField
					:model-value="dxWorkflowInput.activeFilter.value.keywords as string"
					placeholder="Search..."
					:loading="dxWorkflowInput.isLoadingList.value"
					:debounce="500"
					@update:model-value="keywords => dxWorkflowInput.setActiveFilter({keywords })"
				>
					<template #prepend>
						<SearchIcon class="w-4" />
					</template>
				</TextField>
			</div>
			<ListTransition class="mt-4">
				<template v-for="workflowInput in dxWorkflowInput.pagedItems.value?.data || []" :key="workflowInput?.id">
					<WorkflowInputCard
						:workflow-input="workflowInput"
						selectable
						removable
						@select="$emit('confirm', workflowInput)"
						@remove="deleteAction.trigger(workflowInput)"
					/>
					<QSeparator class="bg-slate-400 my-4" />
				</template>
			</ListTransition>
			<div>
				<ActionButton
					type="create"
					color="green"
					label="Create Input"
					:action="createAction"
				/>
			</div>
		</div>
	</InfoDialog>
</template>
<script setup lang="ts">
import { dxWorkflowInput } from "@/components/Modules/Workflows/WorkflowInputs";
import WorkflowInputCard from "@/components/Modules/Workflows/WorkflowInputs/WorkflowInputCard";
import { FaSolidMagnifyingGlass as SearchIcon } from "danx-icon";
import { ActionButton, InfoDialog, ListTransition, TextField } from "quasar-ui-danx";
import { onMounted } from "vue";

defineProps<{ loading?: boolean; saving?: boolean; }>();

defineEmits(["confirm", "close"]);

dxWorkflowInput.activeFilter.value = {
	keywords: ""
};

// Modify the 'create' action behavior so we reload the list and select the created item
const createAction = dxWorkflowInput.getAction("quick-create", { onFinish: dxWorkflowInput.loadList });
const deleteAction = dxWorkflowInput.getAction("quick-delete", { onFinish: dxWorkflowInput.loadList });

onMounted(() => {
	dxWorkflowInput.initialize();
	dxWorkflowInput.setPagination({ rowsPerPage: 10 });
	dxWorkflowInput.loadList();
});
</script>
