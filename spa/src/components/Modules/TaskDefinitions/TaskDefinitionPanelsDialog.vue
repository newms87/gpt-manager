<template>
	<PanelsDrawer
		:title="taskDefinition.name"
		:panels="dxTaskDefinition.panels"
		:target="taskDefinition"
		position="right"
		drawer-class="w-[80vw]"
		@close="$emit('close')"
	/>
</template>

<script lang="ts" setup>
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions/config";
import { TaskDefinition } from "@/types";
import { PanelsDrawer } from "quasar-ui-danx";
import { onMounted } from "vue";

export interface AgentPanelsDialogProps {
	taskDefinition: TaskDefinition;
}

defineEmits(["close"]);
const props = defineProps<AgentPanelsDialogProps>();

onMounted(async () => {
	await dxTaskDefinition.routes.detailsAndStore(props.taskDefinition);
	await dxTaskDefinition.loadFieldOptions();
});
</script>
