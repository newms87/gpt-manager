<template>
	<InfoDialog
		v-if="isShowing"
		title="MCP Server Manager"
		content-class="w-[85vw] h-[85vh] overflow-hidden bg-slate-950"
		@close="$emit('close')"
	>
		<div class="h-full flex flex-col">
			<!-- Header Section -->
			<div class="bg-gradient-to-br from-slate-800 to-slate-900 p-6 rounded-xl shadow-xl flex-shrink-0">
				<div class="flex-x justify-between items-center mb-4">
					<div>
						<h2 class="text-xl font-semibold text-slate-100">MCP Server Configuration</h2>
						<p class="text-sm text-slate-400 mt-1">Manage Model Context Protocol servers for AI agent integration</p>
					</div>
					<ActionButton
						type="create"
						color="green"
						@click="showCreateDialog = true"
					/>
				</div>

				<!-- Quick Stats -->
				<div class="grid grid-cols-3 gap-4">
					<div class="bg-slate-900/50 rounded-lg p-4 border border-slate-700">
						<div class="flex-x justify-between mb-2">
							<ServerIcon class="w-5 text-blue-400" />
							<LabelPillWidget label="Total" color="blue" size="xs" />
						</div>
						<div class="text-2xl font-bold text-blue-300">{{ mcpServers?.length || 0 }}</div>
						<div class="text-xs text-slate-400 mt-1">Configured servers</div>
					</div>

					<div class="bg-slate-900/50 rounded-lg p-4 border border-slate-700">
						<div class="flex-x justify-between mb-2">
							<ActiveIcon class="w-5 text-green-400" />
							<LabelPillWidget label="Active" color="green" size="xs" />
						</div>
						<div class="text-2xl font-bold text-green-300">{{ activeServersCount }}</div>
						<div class="text-xs text-slate-400 mt-1">Ready to use</div>
					</div>

					<div class="bg-slate-900/50 rounded-lg p-4 border border-slate-700">
						<div class="flex-x justify-between mb-2">
							<InactiveIcon class="w-5 text-red-400" />
							<LabelPillWidget label="Inactive" color="red" size="xs" />
						</div>
						<div class="text-2xl font-bold text-red-300">{{ inactiveServersCount }}</div>
						<div class="text-xs text-slate-400 mt-1">Disabled servers</div>
					</div>
				</div>
			</div>

			<!-- MCP Servers Table -->
			<div class="flex-grow overflow-hidden mt-6">
				<ActionTableLayout
					:controls="dxMcpServer"
					:refresh-data="refreshData"
					table-class="h-full"
				/>
			</div>
		</div>

		<!-- Create MCP Server Dialog -->
		<RenderedFormDialog
			v-if="showCreateDialog"
			title="Create MCP Server"
			:form-data="{}"
			:fields="dxMcpServer.fields"
			@close="showCreateDialog = false"
			@submit="onCreateMcpServer"
		/>
	</InfoDialog>
</template>

<script setup lang="ts">
import { dxMcpServer } from "@/components/Modules/McpServers";
import { McpServer } from "@/types";
import {
	FaSolidCircleCheck as ActiveIcon,
	FaSolidServer as ServerIcon,
	FaSolidX as InactiveIcon
} from "danx-icon";
import { ActionButton, ActionTableLayout, InfoDialog, LabelPillWidget, RenderedFormDialog } from "quasar-ui-danx";
import { computed, onMounted, ref } from "vue";

defineProps<{
	isShowing?: boolean;
}>();

defineEmits<{
	close: [];
}>();

const showCreateDialog = ref(false);
const mcpServers = ref<McpServer[]>([]);

const activeServersCount = computed(() => 
	mcpServers.value?.filter(server => server.is_active).length || 0
);

const inactiveServersCount = computed(() => 
	mcpServers.value?.filter(server => !server.is_active).length || 0
);

async function refreshData() {
	const response = await dxMcpServer.routes.list();
	mcpServers.value = response.data;
}

async function onCreateMcpServer(formData: McpServer) {
	await dxMcpServer.getAction("create").trigger(null, formData);
	showCreateDialog.value = false;
	await refreshData();
}

onMounted(refreshData);
</script>