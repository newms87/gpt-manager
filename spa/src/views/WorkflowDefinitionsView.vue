<template>
	<div class="relative h-full overflow-hidden flex flex-col flex-nowrap">
		<div class="flex flex-nowrap items-center p-3">
			<WorkflowDefinitionSelectionBar class="flex-grow" />
			<WorkflowDefinitionHeaderBar v-if="activeWorkflowDefinition" />
		</div>
		<div class="flex flex-grow items-center justify-center overflow-hidden">
			<!-- Error State Display -->
			<div
				v-if="workflowLoadError"
				class="flex flex-col items-center justify-center p-8 text-center bg-red-900/20 rounded-lg border border-red-700/30 m-4"
			>
				<div class="text-red-200 text-lg font-medium mb-2">
					Workflow Loading Error
				</div>
				<div class="text-red-300 mb-4">
					{{ workflowLoadError }}
				</div>
				<button
					class="px-4 py-2 bg-red-700 hover:bg-red-600 text-red-100 rounded transition-colors"
					@click="workflowLoadError = null"
				>
					Dismiss
				</button>
			</div>

			<!-- Normal Editor View -->
			<WorkflowDefinitionEditor
				v-else-if="activeWorkflowDefinition"
				:is-read-only="isReadOnly"
			/>

			<!-- No Workflow Selected State -->
			<div
				v-else
				class="flex items-center justify-center text-slate-400 text-lg"
			>
				Select a workflow to get started
			</div>
		</div>
	</div>
</template>
<script setup lang="ts">
import {
	WorkflowDefinitionEditor,
	WorkflowDefinitionHeaderBar,
	WorkflowDefinitionSelectionBar
} from "@/components/Modules/WorkflowDefinitions";
import { activeWorkflowDefinition, initWorkflowState, isReadOnly, setActiveWorkflowDefinition, workflowLoadError } from "@/components/Modules/WorkflowDefinitions/store";
import { useRoute, useRouter } from "vue-router";
import { onMounted, watch } from "vue";

const route = useRoute();
const router = useRouter();

onMounted(() => {
	const workflowId = route.params.id ? parseInt(route.params.id as string) : null;
	initWorkflowState(workflowId, router);
});

watch(() => route.params.id, (newId) => {
	if (newId) {
		setActiveWorkflowDefinition(parseInt(newId as string), router, true);
	}
});
</script>
