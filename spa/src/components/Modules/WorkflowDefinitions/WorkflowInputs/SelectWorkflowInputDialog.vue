<template>
	<InfoDialog
		:disabled="loading || saving"
		title="Select Input"
		content-class="w-[50rem]"
		done-class="bg-slate-700"
		@close="$emit('close')"
	>
		<div :class="{'opacity-50': loading || saving}">
			<div>
				<TextField
					v-model="filter.keywords"
					placeholder="Search..."
					:loading="isLoading"
					:debounce="500"
					@update:model-value="loadWorkflowInputs"
				>
					<template #prepend>
						<SearchIcon class="w-4" />
					</template>
				</TextField>
			</div>
			<ListTransition class="mt-4">
				<template v-for="workflowInput in workflowInputs" :key="workflowInput.id">
					<WorkflowInputCard
						:workflow-input="workflowInput"
						selectable
						editable-team-objects
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
import { dxWorkflowInput } from "@/components/Modules/WorkflowDefinitions/WorkflowInputs";
import WorkflowInputCard from "@/components/Modules/WorkflowDefinitions/WorkflowInputs/WorkflowInputCard";
import { FaSolidMagnifyingGlass as SearchIcon } from "danx-icon";
import { ActionButton, InfoDialog, ListTransition, TextField } from "quasar-ui-danx";
import { onMounted, ref, shallowRef } from "vue";

defineProps<{ loading?: boolean; saving?: boolean; }>();

defineEmits(["confirm", "close"]);

dxWorkflowInput.activeFilter.value = {
	keywords: ""
};

// Modify the 'create' action behavior so we reload the list and select the created item
const createAction = dxWorkflowInput.getAction("quick-create", { onFinish: loadWorkflowInputs });
const deleteAction = dxWorkflowInput.getAction("quick-delete", { onFinish: loadWorkflowInputs });

const isLoading = ref(false);
const workflowInputs = shallowRef([]);
const filter = ref({
	keywords: ""
});
onMounted(loadWorkflowInputs);

async function loadWorkflowInputs() {
	isLoading.value = true;
	workflowInputs.value = (await dxWorkflowInput.routes.list({
		fields: { files: { thumb: true, transcodes: true }, content: true, teamObject: true },
		filter: filter.value,
		perPage: 10
	})).data;
	isLoading.value = false;
}
</script>
