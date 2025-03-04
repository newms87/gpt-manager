<template>
	<PanelsDrawer
		:title="taskDefinition.name"
		:panels="dxTaskDefinition.panels"
		:target="taskDefinition"
		position="standard"
		drawer-class="w-[80vw]"
		@close="$emit('close')"
	/>
</template>

<script lang="ts" setup>
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions/config";
import { TaskDefinition } from "@/types";
import { PanelsDrawer } from "quasar-ui-danx";

export interface AgentPanelsDialogProps {
	taskDefinition: TaskDefinition;
}

defineEmits(["close"]);
const props = defineProps<AgentPanelsDialogProps>();
dxTaskDefinition.routes.detailsAndStore(props.taskDefinition);
dxTaskDefinition.loadFieldOptions();
</script>
