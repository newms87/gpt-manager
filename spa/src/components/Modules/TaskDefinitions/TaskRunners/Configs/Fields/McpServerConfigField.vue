<template>
	<div>
		<SelectionMenuField
			v-model:selected="selectedMcpServer"
			selectable
			creatable
			class="w-full"
			:select-icon="ServerIcon"
			select-text="MCP Server"
			empty-text="No MCP servers configured. Create one to get started."
			label-class="text-slate-300"
			:options="availableMcpServers"
			:loading="isLoading"
			@create="onCreateMcpServer"
		>
			<template #selected="{ item }">
				<McpServerCard :mcp-server="item" class="mb-2" />
			</template>
			<template #option="{ item }">
				<McpServerCard :mcp-server="item" />
			</template>
		</SelectionMenuField>

		<McpServerManagerDialog
			v-if="showMcpManager"
			@close="onCloseManager"
		/>
	</div>
</template>

<script setup lang="ts">
import { dxMcpServer } from "@/components/Modules/McpServers";
import McpServerCard from "@/components/Modules/McpServers/McpServerCard.vue";
import McpServerManagerDialog from "@/components/Modules/McpServers/McpServerManagerDialog";
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions/config";
import { McpServer, TaskDefinition } from "@/types";
import { FaSolidServer as ServerIcon } from "danx-icon";
import { SelectionMenuField } from "quasar-ui-danx";
import { computed, onMounted, ref } from "vue";

const props = defineProps<{
	taskDefinition: TaskDefinition;
}>();

const mcpServers = ref<McpServer[]>([]);
const showMcpManager = ref(false);

const isLoading = ref(false);

const selectedMcpServerId = computed(() =>
	props.taskDefinition.task_runner_config?.mcp_server_id || null
);

const availableMcpServers = computed(() =>
	mcpServers.value
);

const selectedMcpServer = computed({
	get: () => mcpServers.value.find(server =>
		server.id === selectedMcpServerId.value
	) || null,
	set: async (server: McpServer | null) => {
		// Extract just the ID from the server object
		const serverId = server?.id || null;
		
		const updatedConfig = {
			...props.taskDefinition.task_runner_config,
			mcp_server_id: serverId
		};

		await dxTaskDefinition.getAction("update").trigger(props.taskDefinition, {
			task_runner_config: updatedConfig
		});
	}
});

async function loadMcpServers() {
	isLoading.value = true;
	try {
			const response = await dxMcpServer.routes.list();
		// Filter out any invalid entries (like empty arrays or malformed data)
		mcpServers.value = (response.data || []).filter(server => 
			server && typeof server === 'object' && server.id && server.name
		);
	} catch (error) {
		console.error("Failed to load MCP servers:", error);
	} finally {
		isLoading.value = false;
	}
}


function onCreateMcpServer() {
	showMcpManager.value = true;
}

async function onCloseManager() {
	showMcpManager.value = false;
	await loadMcpServers();
}

onMounted(loadMcpServers);
</script>
