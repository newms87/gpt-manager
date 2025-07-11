<template>
	<div>
		<!-- Selected MCP Server -->
		<div v-if="selectedMcpServer" class="cursor-pointer" @click="showMcpManager = true">
			<McpServerCard :mcp-server="selectedMcpServer" />
		</div>
		
		<!-- Empty State -->
		<div 
			v-else 
			class="bg-slate-800/50 rounded-lg p-6 border-2 border-dashed border-slate-700 hover:border-slate-600 transition-colors cursor-pointer text-center"
			@click="showMcpManager = true"
		>
			<ServerIcon class="w-8 h-8 text-slate-500 mx-auto mb-2" />
			<div class="text-slate-400">No MCP server selected</div>
			<div class="text-sm text-slate-500 mt-1">Click to select a server</div>
		</div>

		<!-- MCP Server Manager Dialog -->
		<McpServerManagerDialog
			v-if="showMcpManager"
			:selected-server-id="selectedMcpServerId"
			@close="showMcpManager = false"
			@select="onSelectServer"
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
import { computed, onMounted, ref } from "vue";

const props = defineProps<{
	taskDefinition: TaskDefinition;
}>();

const mcpServers = ref<McpServer[]>([]);
const showMcpManager = ref(false);

const selectedMcpServerId = computed(() => 
	props.taskDefinition.task_runner_config?.mcp_server_id || null
);

const selectedMcpServer = computed(() => 
	mcpServers.value.find(server => server.id === selectedMcpServerId.value) || null
);

async function onSelectServer(server: McpServer | null) {
	const updatedConfig = {
		...props.taskDefinition.task_runner_config,
		mcp_server_id: server?.id || null
	};

	await dxTaskDefinition.getAction("update").trigger(props.taskDefinition, {
		task_runner_config: updatedConfig
	});
	
	showMcpManager.value = false;
	await loadMcpServers();
}

async function loadMcpServers() {
	const response = await dxMcpServer.routes.list();
	mcpServers.value = response.data || [];
}

onMounted(loadMcpServers);
</script>