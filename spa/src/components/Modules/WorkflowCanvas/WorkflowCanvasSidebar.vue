<template>
	<CollapsableSidebar
		v-model:collapse="isCollapsed"
		class="workflow-canvas-sidebar"
		right-side
		min-width="3.5rem"
		max-width="20rem"
		name="workflow-canvas-sidebar"
	>
		<WorkflowSidebarTaskRunnerClassList
			:workflow-definition="workflowDefinition"
			:collapsed="isCollapsed"
		/>
	</CollapsableSidebar>
</template>

<script setup lang="ts">
import WorkflowSidebarTaskRunnerClassList from "@/components/Modules/WorkflowCanvas/WorkflowSidebarTaskRunnerClassList";
import { WorkflowDefinition } from "@/types";
import { CollapsableSidebar } from "quasar-ui-danx";
import { ref } from "vue";

defineProps<{
	workflowDefinition: WorkflowDefinition;
}>();

const isCollapsed = ref(false);
</script>
