<template>
	<div class="relative h-full overflow-hidden flex flex-col flex-nowrap">
		<div class="flex flex-nowrap space-x-4 p-3">
			<SelectionMenuField
				:selected="activeWorkflowDefinition"
				selectable
				name-editable
				creatable
				deletable
				:select-icon="WorkflowIcon"
				label-class="text-slate-300"
				:options="workflowDefinitions"
				:loading="isLoadingWorkflowDefinitions"
				@update:selected="(workflowDefinition: WorkflowDefinition) => setActiveWorkflowDefinition(workflowDefinition)"
				@create="createAction.trigger"
				@update="input => updateAction.trigger(activeWorkflowDefinition, input)"
				@delete="workflowDefinition => deleteAction.trigger(workflowDefinition)"
			/>
			<div class="flex-grow">
				<ActionButton
					type="export"
					color="sky-invert"
					@click="onExportToJson"
				/>
			</div>
			<WorkflowDefinitionHeaderBar v-if="activeWorkflowDefinition" />
		</div>
		<div class="flex flex-grow items-center justify-center overflow-hidden">
			<WorkflowDefinitionEditor v-if="activeWorkflowDefinition" />
		</div>
	</div>
</template>
<script setup lang="ts">
import {
	dxWorkflowDefinition,
	WorkflowDefinitionEditor,
	WorkflowDefinitionHeaderBar
} from "@/components/Modules/WorkflowDefinitions";
import {
	activeWorkflowDefinition,
	initWorkflowState,
	isLoadingWorkflowDefinitions,
	loadWorkflowDefinitions,
	setActiveWorkflowDefinition,
	workflowDefinitions
} from "@/components/Modules/WorkflowDefinitions/store";
import { WorkflowDefinition } from "@/types";
import { FaSolidAnkh as WorkflowIcon } from "danx-icon";
import { ActionButton, download, FlashMessages, SelectionMenuField } from "quasar-ui-danx";
import { onMounted, ref } from "vue";

onMounted(initWorkflowState);

const createAction = dxWorkflowDefinition.getAction("quick-create", { onFinish: loadWorkflowDefinitions });
const updateAction = dxWorkflowDefinition.getAction("update");
const deleteAction = dxWorkflowDefinition.getAction("delete", { onFinish: loadWorkflowDefinitions });

const isExporting = ref(false);
async function onExportToJson() {
	isExporting.value = true;
	const json = await dxWorkflowDefinition.routes.exportToJson(activeWorkflowDefinition.value);
	isExporting.value = false;
	if (json.error) {
		FlashMessages.error(json.message);
	} else if (!json.definition) {
		FlashMessages.error("The export failed for an unknown reason. The data was empty.");
	} else {
		download(JSON.stringify(json), `${activeWorkflowDefinition.value.name}.json`);
	}
}
</script>
