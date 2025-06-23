<template>
	<ActionTableLayout
		title="WhatsApp Messages"
		:controller="dxWhatsAppMessage"
		class="whatsapp-messages-list"
		refresh-button
	>

		<template #table-top>
			<div class="flex items-center gap-4 p-4 bg-slate-800 border-b border-slate-600">
				<SelectField
					v-model="filters.connection_id"
					label="Connection"
					:options="connectionOptions"
					:loading="loadingConnections"
					clearable
					class="min-w-48"
				/>

				<SelectField
					v-model="filters.direction"
					label="Direction"
					:options="directionOptions"
					clearable
					class="min-w-32"
				/>

				<SelectField
					v-model="filters.status"
					label="Status"
					:options="statusOptions"
					clearable
					class="min-w-32"
				/>

				<TextField
					v-model="filters.phone_number"
					label="Phone Number"
					placeholder="+1234567890"
					clearable
					class="min-w-48"
				/>

				<QBtn
					color="primary"
					@click="applyFilters"
					:loading="dxWhatsAppMessage.isLoading"
				>
					<FaSolidMagnifyingGlass class="w-3 h-3 mr-1" />
					Filter
				</QBtn>
			</div>
		</template>
	</ActionTableLayout>
</template>

<script setup lang="ts">
import { ActionTableLayout, SelectField, TextField } from "quasar-ui-danx";
import { dxWhatsAppMessage } from "./config";
import { FaSolidMagnifyingGlass } from "danx-icon";
import { ref, onMounted, computed } from "vue";
import { request } from "quasar-ui-danx";
import { WhatsAppConnection } from "@/types/whatsapp";
import { useWhatsAppStore } from "./store";

dxWhatsAppMessage.initialize();

const loadingConnections = ref(false);
const connections = ref<WhatsAppConnection[]>([]);

const filters = ref({
	connection_id: null,
	direction: null,
	status: null,
	phone_number: ''
});

const connectionOptions = computed(() => {
	return connections.value.map(connection => ({
		label: `${connection.name} (${connection.display_phone_number})`,
		value: connection.id
	}));
});

const directionOptions = [
	{ label: 'Inbound', value: 'inbound' },
	{ label: 'Outbound', value: 'outbound' }
];

const statusOptions = [
	{ label: 'Pending', value: 'pending' },
	{ label: 'Sent', value: 'sent' },
	{ label: 'Delivered', value: 'delivered' },
	{ label: 'Read', value: 'read' },
	{ label: 'Failed', value: 'failed' }
];


async function loadConnections() {
	loadingConnections.value = true;
	
	try {
		const response = await request.get('/api/whatsapp-connections');
		connections.value = response.data || [];
	} catch (error) {
		console.error('Failed to load WhatsApp connections:', error);
		connections.value = [];
	} finally {
		loadingConnections.value = false;
	}
}

function applyFilters() {
	const params = {};
	
	if (filters.value.connection_id) {
		params.whatsapp_connection_id = filters.value.connection_id;
	}
	
	if (filters.value.direction) {
		params.direction = filters.value.direction;
	}
	
	if (filters.value.status) {
		params.status = filters.value.status;
	}
	
	if (filters.value.phone_number) {
		params.phone_number = filters.value.phone_number;
	}
	
	dxWhatsAppMessage.setFilter(params);
	dxWhatsAppMessage.loadListData();
}

onMounted(() => {
	loadConnections();
	
	// Set up real-time updates
	const { setupWhatsAppListeners } = useWhatsAppStore();
	setupWhatsAppListeners();
});
</script>

<style lang="scss" scoped>
.whatsapp-messages-list {
	.q-table {
		background: theme('colors.slate.800');
		color: theme('colors.slate.200');
	}
}
</style>