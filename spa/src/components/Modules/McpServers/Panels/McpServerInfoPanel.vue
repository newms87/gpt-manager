<template>
	<div class="p-6">
		<ActionForm
			:action="updateAction"
			:form-data="mcpServer"
			:fields="dxMcpServer.fields"
			@submit="onUpdate"
		/>
	</div>
</template>

<script setup lang="ts">
import { McpServer } from "@/types";
import { ActionForm } from "quasar-ui-danx";
import { dxMcpServer } from "../config";

const props = defineProps<{
	mcpServer: McpServer;
}>();

const updateAction = dxMcpServer.getAction("update");

async function onUpdate(formData: McpServer) {
	await updateAction.trigger(props.mcpServer, formData);
}
</script>