<template>
	<InfoDialog
		title="Select Input"
		content-class="w-[50rem]"
		done-class="bg-slate-700"
		@close="$emit('close')"
	>
		<div>
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
			<template v-for="workflowInput in dxWorkflowInput.pagedItems.value?.data || []" :key="workflowInput?.id">
				<WorkflowInputCard :workflow-input="workflowInput" readonly @select="$emit('confirm', workflowInput)" />
				<QSeparator class="bg-slate-400 my-4" />
			</template>
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
import { ActionButton } from "@/components/Shared";
import { FaSolidMagnifyingGlass as SearchIcon } from "danx-icon";
import { InfoDialog, TextField } from "quasar-ui-danx";
import { onMounted } from "vue";

const emit = defineEmits(["confirm", "close"]);

dxWorkflowInput.activeFilter.value = {
	keywords: ""
};

// Modify the 'create' action behavior so we reload the list and select the created item
const createAction = dxWorkflowInput.getAction("create", {
	onFinish: ({ item }) => {
		if (item) {
			emit("confirm", item);
		}
	}
});
onMounted(() => {
	dxWorkflowInput.initialize();
	dxWorkflowInput.setPagination({ rowsPerPage: 10 });
	dxWorkflowInput.loadList();
});
</script>
