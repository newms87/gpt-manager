<template>
	<div class="relative h-full overflow-hidden flex flex-col flex-nowrap">
		<div class="flex flex-nowrap items-center p-3">
			<WorkflowDefinitionSelectionBar class="flex-grow" />
			<WorkflowDefinitionHeaderBar v-if="activeWorkflowDefinition" />
		</div>
		<div class="flex flex-grow items-center justify-center overflow-hidden">
			<WorkflowDefinitionEditor v-if="activeWorkflowDefinition" />
		</div>
	</div>
</template>
<script setup lang="ts">
import {
	WorkflowDefinitionEditor,
	WorkflowDefinitionHeaderBar,
	WorkflowDefinitionSelectionBar
} from "@/components/Modules/WorkflowDefinitions";
import { activeWorkflowDefinition } from "@/components/Modules/WorkflowDefinitions/store";
</script>
