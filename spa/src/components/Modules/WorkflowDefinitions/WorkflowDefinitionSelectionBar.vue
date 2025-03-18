<template>
	<div class="flex flex-nowrap space-x-4">
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
		<div class="flex space-x-4">
			<ActionButton
				v-if="activeWorkflowDefinition"
				type="export"
				color="sky-invert"
				label="Export"
				:saving="isExporting"
				@click="onExportToJson"
			/>
			<ActionButton
				type="import"
				color="sky"
				label="Import"
				:saving="isImporting"
				@click="fileInput.click()"
			/>
			<input
				ref="fileInput"
				type="file"
				accept="application/json"
				hidden="hidden"
				@change="onImportFromJson"
			/>
		</div>
	</div>
</template>
<script setup lang="ts">
import { dxWorkflowDefinition } from "@/components/Modules/WorkflowDefinitions";
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
import { ActionButton, download, FlashMessages, importJson, SelectionMenuField } from "quasar-ui-danx";
import { onMounted, ref } from "vue";

onMounted(initWorkflowState);

const createAction = dxWorkflowDefinition.getAction("quick-create", { onFinish: loadWorkflowDefinitions });
const updateAction = dxWorkflowDefinition.getAction("update");
const deleteAction = dxWorkflowDefinition.getAction("delete", { onFinish: loadWorkflowDefinitions });

const isExporting = ref(false);
const isImporting = ref(false);
const fileInput = ref();

async function onExportToJson() {
	isExporting.value = true;
	try {
		const json = await dxWorkflowDefinition.routes.exportToJson(activeWorkflowDefinition.value);

		if (json.error) {
			FlashMessages.error(json.message);
		} else if (!json.definitions) {
			FlashMessages.error("The export failed for an unknown reason. The data was empty.");
		} else {
			download(JSON.stringify(json), `${activeWorkflowDefinition.value.name}.json`);
		}
	} finally {
		isExporting.value = false;
	}
}

async function onImportFromJson(event: Event) {
	const files = event.target?.files;
	if (!files?.length) return;

	isImporting.value = true;

	try {
		const jsonData = await importJson(files[0]);
		const workflowDefinition = await dxWorkflowDefinition.routes.importFromJson(jsonData);

		if (workflowDefinition?.id) {
			await loadWorkflowDefinitions();
			await setActiveWorkflowDefinition(workflowDefinition);
		} else {
			FlashMessages.error("The import failed for an unknown reason. The data was empty.");
		}
	} finally {
		isImporting.value = false;
	}
}
</script>
