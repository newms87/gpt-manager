<template>
	<InfoDialog
		content-class="w-[80vw]"
		@close="$emit('close')"
	>
		<template #title>
			<div class="flex-grow">
				<EditableDiv
					:model-value="taskDefinition.name"
					color="slate-600"
					@update:model-value="name => updateTaskDefinitionAction.trigger(taskDefinition, {name})"
				/>
			</div>
			<SaveStateIndicator :saving="taskDefinition.isSaving" :saved-at="taskDefinition.updated_at" />
		</template>
		<TaskDefinitionConfigForm
			name="edit"
			:task-definition="taskDefinition"
			:workflow-node="workflowNode"
		/>
	</InfoDialog>
</template>

<script lang="ts" setup>
import { TaskDefinitionConfigForm } from "@/components/Modules/TaskDefinitions";
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions/config";
import { TaskDefinition, WorkflowNode } from "@/types";
import { EditableDiv, InfoDialog, SaveStateIndicator } from "quasar-ui-danx";
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

const updateTaskDefinitionAction = dxTaskDefinition.getAction("update");
</script>
