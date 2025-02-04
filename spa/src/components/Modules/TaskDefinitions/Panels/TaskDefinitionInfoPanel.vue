<template>
	<div class="p-6">
		<ActionForm
			:action="updateAction"
			:target="taskDefinition"
			:form="{fields}"
		/>
	</div>
</template>
<script setup lang="ts">
import { fields } from "@/components/Modules/Agents/config/fields";
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions";
import { TaskDefinition } from "@/types";
import { ActionForm } from "quasar-ui-danx";

defineProps<{
	taskDefinition: TaskDefinition,
}>();

const updateAction = dxTaskDefinition.getAction("update-debounced");
</script>
