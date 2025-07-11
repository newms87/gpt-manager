<template>
	<div>
		<div class="flex-x justify-between items-center mb-3">
			<div>
				<div class="text-base font-medium text-slate-200">MCP Servers</div>
				<div class="text-sm text-slate-400 mt-1">Enable Model Context Protocol servers for enhanced capabilities</div>
			</div>
			<ActionButton
				type="settings"
				color="slate"
				size="sm"
				tooltip="Manage MCP Servers"
				@click="showMcpManager = true"
			/>
		</div>

		<SelectField
			:model-value="selectedMcpServerIds"
			:options="mcpServerOptions"
			multiple
			clearable
			placeholder="Select MCP servers to enable..."
			@update:model-value="onSelectionChange"
		/>

		<div v-if="selectedMcpServers.length > 0" class="mt-4">
			<div class="text-sm font-medium text-slate-300 mb-2">Selected Servers:</div>
			<div class="space-y-2">
				<div
					v-for="server in selectedMcpServers"
					:key="server.id"
					class="bg-slate-800/50 rounded-lg p-3 border border-slate-700"
				>
					<div class="flex-x justify-between items-start">
						<div>
							<div class="font-medium text-slate-200">{{ server.name }}</div>
							<div class="text-xs text-slate-400">{{ server.server_url }}</div>
							<div v-if="server.allowed_tools?.length" class="text-xs text-slate-500 mt-1">
								Tools: {{ server.allowed_tools.join(", ") }}
							</div>
						</div>
						<LabelPillWidget
							:label="server.require_approval"
							:color="server.require_approval === 'never' ? 'green' : 'orange'"
							size="xs"
						/>
					</div>
				</div>
			</div>
		</div>

		<McpServerManagerDialog
			:is-showing="showMcpManager"
			@close="showMcpManager = false"
		/>
	</div>
</template>

<script setup lang="ts">
import { dxMcpServer } from "@/components/Modules/McpServers";
import McpServerManagerDialog from "@/components/Modules/McpServers/McpServerManagerDialog";
import { dxTaskDefinition } from "@/components/Modules/TaskDefinitions/config";
import { McpServer, TaskDefinition } from "@/types";
import { ActionButton, LabelPillWidget, SelectField } from "quasar-ui-danx";
import { computed, onMounted, ref } from "vue";

const props = defineProps<{
	taskDefinition: TaskDefinition;
}>();

const showMcpManager = ref(false);
const mcpServers = ref<McpServer[]>([]);

const selectedMcpServerIds = computed(() =>
	props.taskDefinition.task_runner_config?.mcp_server_ids || []
);

const mcpServerOptions = computed(() =>
	mcpServers.value
		.filter(server => server.is_active)
		.map(server => ({
			value: server.id,
			label: server.name,
			description: server.server_url
		}))
);

const selectedMcpServers = computed(() =>
	mcpServers.value.filter(server =>
		selectedMcpServerIds.value.includes(server.id)
	)
);

async function loadMcpServers() {
	try {
		const response = await dxMcpServer.routes.list();
		mcpServers.value = response.data;
	} catch (error) {
		console.error("Failed to load MCP servers:", error);
	}
}

async function onSelectionChange(serverIds: string[]) {
	const updatedConfig = {
		...props.taskDefinition.task_runner_config,
		mcp_server_ids: serverIds
	};

	await dxTaskDefinition.getAction("update").trigger(props.taskDefinition, {
		task_runner_config: updatedConfig
	});
}

onMounted(loadMcpServers);
</script>
