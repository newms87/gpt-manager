<template>
	<InfoDialog
		title="MCP Server Manager"
		content-class="w-[85vw] h-[85vh] overflow-hidden bg-slate-950"
		@close="emit('close')"
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
						@click="onCreateButtonClick"
					/>
				</div>

				<!-- Quick Stats -->
				<div class="grid grid-cols-1 gap-4">
					<div class="bg-slate-900/50 rounded-lg p-4 border border-slate-700">
						<div class="flex-x justify-between mb-2">
							<ServerIcon class="w-5 text-blue-400" />
							<LabelPillWidget label="Total" color="blue" size="xs" />
						</div>
						<div class="text-2xl font-bold text-blue-300">{{ pagination.total || 0 }}</div>
						<div class="text-xs text-slate-400 mt-1">MCP servers configured</div>
					</div>
				</div>
			</div>

			<!-- Search and Controls -->
			<div class="flex-x justify-between items-center mt-6 mb-4">
				<SearchBox
					v-model="searchKeyword"
					placeholder="Search MCP servers..."
					class="flex-1 max-w-md"
				/>
			</div>

			<!-- MCP Servers List -->
			<div class="flex-grow overflow-hidden">
				<template v-if="!mcpServers.length && isLoading">
					<QSkeleton v-for="i in pagination.perPage" :key="i" class="h-24 my-3" />
				</template>
				<div v-else-if="mcpServers.length === 0" class="text-center py-8 text-slate-400">
					<div v-if="searchKeyword">No servers found matching "{{ searchKeyword }}"</div>
					<div v-else>No MCP servers configured</div>
				</div>
				<div v-else class="relative h-full overflow-y-auto">
					<LoadingOverlay v-if="isLoading && mcpServers.length > 0" />

					<div class="space-y-3">
						<McpServerCard
							v-for="server in mcpServers"
							:key="server.id"
							:mcp-server="server"
							show-actions
							show-select
							:is-selected="server.id === props.selectedServerId"
							:class="{ 'ring-2 ring-blue-500': server.id === props.selectedServerId }"
							@edit="onEditServer"
							@delete="onDeleteServer"
							@select="emit('select', $event)"
						/>
					</div>
				</div>

				<!-- Pagination -->
				<PaginationNavigator
					v-if="pagination.total > pagination.perPage"
					v-model="pagination"
					class="mt-4"
				/>
			</div>
		</div>

		<!-- MCP Server Dialog -->
		<RenderedFormDialog
			v-if="showDialog"
			v-model="dialogFormData"
			:title="dialogTitle"
			:confirm-text="dialogTitle"
			content-class="w-[600px]"
			:form="{ fields: dxMcpServer.fields }"
			@close="closeDialog"
			@confirm="onSaveMcpServer"
		/>
	</InfoDialog>
</template>

<script setup lang="ts">
import { dxMcpServer } from "@/components/Modules/McpServers";
import McpServerCard from "@/components/Modules/McpServers/McpServerCard";
import { LoadingOverlay, PaginationNavigator, SearchBox } from "@/components/Shared";
import { McpServer } from "@/types";
import { PaginationModel } from "@/types/Pagination";
import { FaSolidServer as ServerIcon } from "danx-icon";
import { QSkeleton } from "quasar";
import { ActionButton, InfoDialog, LabelPillWidget, ListControlsPagination, RenderedFormDialog } from "quasar-ui-danx";
import { computed, onMounted, ref, shallowRef, watch } from "vue";

const dialogTitle = computed(() => editingServer.value ? "Edit MCP Server" : "Create MCP Server");

const props = defineProps<{
	selectedServerId?: string | null;
}>();

const emit = defineEmits<{
	close: [];
	select: [server: McpServer | null];
}>();

const showDialog = ref(false);
const mcpServers = shallowRef<McpServer[]>([]);
const isLoading = ref(false);
const searchKeyword = ref("");
const editingServer = ref<McpServer | null>(null);
const dialogFormData = ref<McpServer | {}>({});

const pagination = ref<PaginationModel>({
	page: 1,
	perPage: 6,
	total: 0
});

const filters = computed(() => ({
	keywords: searchKeyword.value
}));

// Watch for changes in pagination or filters to reload data
watch(filters, (value, oldValue) => {
	if (JSON.stringify(value) !== JSON.stringify(oldValue)) {
		pagination.value.page = 1;
		loadMcpServers();
	}
});
watch(pagination, loadMcpServers);

async function loadMcpServers() {
	isLoading.value = true;

	const results = await dxMcpServer.routes.list({
		...pagination.value,
		filter: filters.value
	} as ListControlsPagination);

	// Ignore bad responses (probably an abort or network connection issue)
	if (!results.data) return;

	mcpServers.value = results.data as McpServer[];
	pagination.value.total = results.meta?.total || 0;
	isLoading.value = false;
}

async function refreshData() {
	await loadMcpServers();
}

function onCreateButtonClick() {
	editingServer.value = null;
	dialogFormData.value = {};
	showDialog.value = true;
}

async function onEditServer(server: McpServer) {
	editingServer.value = server;
	dialogFormData.value = { ...server };
	showDialog.value = true;
}

async function onSaveMcpServer(formData: McpServer) {
	if (editingServer.value) {
		await dxMcpServer.getAction("update").trigger(editingServer.value, formData);
	} else {
		await dxMcpServer.getAction("quick-create").trigger(null, formData);
	}
	closeDialog();
	await refreshData();
}

function closeDialog() {
	showDialog.value = false;
	editingServer.value = null;
	dialogFormData.value = {};
}

async function onDeleteServer(server: McpServer) {
	if (confirm(`Are you sure you want to delete "${server.name}"?`)) {
		await dxMcpServer.getAction("delete").trigger(server);
		await refreshData();
		// If we deleted the selected server, emit null selection
		if (server.id === props.selectedServerId) {
			emit('select', null);
		}
	}
}

onMounted(loadMcpServers);
</script>
