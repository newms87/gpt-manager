<template>
	<div class="p-6 h-full overflow-y-auto">
		<QBtn class="bg-green-900 text-slate-300 py-2 px-6" @click="createWorkflowInput">
			<CreateIcon class="w-4 mr-2" />
			New Workflow Input
		</QBtn>

		<QSeparator class="bg-slate-400 my-4" />

		<div v-for="workflowInput in workflowInputs" :key="workflowInput.id">
			<div class="flex items-start flex-nowrap">
				<WorkflowInputCard :workflow-input="workflowInput" class="flex-grow" />
				<div class="flex-x py-6">
					<ShowHideButton
						:model-value="activeWorkflowInput?.id === workflowInput.id"
						:show-icon="ShowWorkflowIcon"
						label=""
						class="bg-green-800 "
						@update:model-value="isActive => activeWorkflowInput = isActive && workflowInput || null"
					/>
					<WorkflowStatusProgressBar class="ml-2" :workflow-input="workflowInput" />
					<ActionButton
						:action="deleteAction"
						:target="workflowInput"
						type="trash"
						class="ml-4"
						tooltip="Delete workflow input"
					/>
				</div>
			</div>
			<div v-if="activeWorkflowInput?.id === workflowInput.id">
				Implement this panel
			</div>
			<QSeparator class="bg-slate-400 my-4" />
		</div>
	</div>
</template>
<script setup lang="ts">
import { TeamObject } from "@/components/Modules/TeamObjects/team-objects";
import WorkflowStatusProgressBar from "@/components/Modules/WorkflowDefinitions/Shared/WorkflowStatusProgressBar";
import { dxWorkflowInput } from "@/components/Modules/WorkflowDefinitions/WorkflowInputs";
import WorkflowInputCard from "@/components/Modules/WorkflowDefinitions/WorkflowInputs/WorkflowInputCard";
import { WorkflowInput } from "@/types";
import { FaSolidPlus as CreateIcon, FaSolidWorm as ShowWorkflowIcon } from "danx-icon";
import { ActionButton, FlashMessages, ShowHideButton, storeObjects } from "quasar-ui-danx";
import { onMounted, Ref, ref } from "vue";

const props = defineProps<{
	teamObject: TeamObject,
}>();

onMounted(loadWorkflowInputs);

const deleteAction = dxWorkflowInput.getAction("delete", { onSuccess: loadWorkflowInputs });

const workflowInputs: Ref<WorkflowInput[]> = ref([]);
const activeWorkflowInput = ref<WorkflowInput>(null);

async function createWorkflowInput() {
	const result = await dxWorkflowInput.routes.applyAction("create", null, {
		name: props.teamObject.name + " Input",
		team_object_id: props.teamObject.id,
		team_object_type: props.teamObject.type
	});


	if (result.success) {
		await loadWorkflowInputs();
	} else {
		FlashMessages.error("Failed to create workflow input" + (result.error ? ": " + result.message : ""));
	}
}

async function loadWorkflowInputs() {
	const result = await dxWorkflowInput.routes.list({
		filter: { team_object_id: props.teamObject.id },
		fields: { files: { thumb: true }, content: true }
	});
	workflowInputs.value = storeObjects(result.data) as WorkflowInput[];
}
</script>
