<template>
	<CollapsableSidebar
		v-model:collapse="isCollapsed"
		class="workflow-canvas-sidebar"
		right-side
		min-width="3.5rem"
		max-width="20rem"
		name="workflow-canvas-sidebar"
	>
		<div>
			<QTabs v-model="currentTab" :class="{'opacity-0': isCollapsed}" class="transition-all">
				<QTab name="operations">Operations</QTab>
				<QTab name="definitions">Task Definitions</QTab>
			</QTabs>
			<WorkflowSidebarTaskRunnerClassList
				v-if="currentTab === 'operations'"
				:workflow-definition="workflowDefinition"
				:collapsed="isCollapsed"
			/>
			<WorkflowSidebarTaskDefinitionList v-else :workflow-definition="workflowDefinition" :collapsed="isCollapsed" />
		</div>
	</CollapsableSidebar>
</template>

<script setup lang="ts">
import WorkflowSidebarTaskDefinitionList from "@/components/Modules/WorkflowCanvas/WorkflowSidebarTaskDefinitionList";
import WorkflowSidebarTaskRunnerClassList from "@/components/Modules/WorkflowCanvas/WorkflowSidebarTaskRunnerClassList";
import { WorkflowDefinition } from "@/types";
import { CollapsableSidebar } from "quasar-ui-danx";
import { ref } from "vue";

defineEmits(["refresh"]);
defineProps<{
	workflowDefinition: WorkflowDefinition;
}>();

const isCollapsed = ref(false);
const currentTab = ref("operations");
</script>
