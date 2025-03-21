<template>
	<PanelsDrawer
		:title="taskDefinition.name"
		:panels="dxTaskDefinition.panels.filter(p => p.name === 'edit')"
		:target="taskDefinition"
		hide-tabs
		position="right"
		drawer-class="w-[80vw]"
		@close="$emit('close')"
	>
		<template #panels>
			<TaskDefinitionConfigPanel
				name="edit"
				:task-definition="taskDefinition"
				:workflow-node="workflowNode"
				class="p-8"
			/>
		</template>
	</PanelsDrawer>
</template>

<script lang="ts" setup>
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions/config";
import { TaskDefinitionConfigPanel } from "@/components/Modules/TaskDefinitions/Panels";
import { TaskDefinition, WorkflowNode } from "@/types";
import { PanelsDrawer } from "quasar-ui-danx";
import { onMounted } from "vue";

export interface AgentPanelsDialogProps {
	taskDefinition: TaskDefinition;
	workflowNode: WorkflowNode;
}

defineEmits(["close"]);
const props = defineProps<AgentPanelsDialogProps>();

onMounted(async () => {
	await dxTaskDefinition.routes.details(props.taskDefinition);
});
</script>
