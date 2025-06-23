<template>
	<div class="flex flex-col gap-6">
		<!-- Connection Overview -->
		<div class="bg-slate-800 border border-slate-600 rounded-lg p-4">
			<div class="flex items-center justify-between mb-4">
				<div class="flex items-center gap-3">
					<div :class="statusIndicatorClass"></div>
					<div>
						<h3 class="text-lg font-semibold text-slate-200">{{ connection.name }}</h3>
						<p class="text-sm text-slate-400">{{ connection.display_phone_number }}</p>
					</div>
				</div>
				
				<div class="flex items-center gap-2">
					<span class="text-xs px-2 py-1 rounded" :class="providerBadgeClass">
						{{ connection.api_provider === 'twilio' ? 'Twilio' : 'WhatsApp Business' }}
					</span>
					<span class="text-xs px-2 py-1 rounded capitalize" :class="statusBadgeClass">
						{{ connection.status }}
					</span>
				</div>
			</div>

			<div class="grid grid-cols-2 gap-4">
				<div>
					<span class="text-xs text-slate-500 block">Messages</span>
					<span class="text-sm text-slate-300">{{ connection.message_count || 0 }}</span>
				</div>
				<div>
					<span class="text-xs text-slate-500 block">Last Verified</span>
					<span class="text-sm text-slate-300">
						{{ connection.verified_at ? fDate(connection.verified_at) : 'Never' }}
					</span>
				</div>
				<div>
					<span class="text-xs text-slate-500 block">Last Sync</span>
					<span class="text-sm text-slate-300">
						{{ connection.last_sync_at ? fDate(connection.last_sync_at) : 'Never' }}
					</span>
				</div>
				<div>
					<span class="text-xs text-slate-500 block">Created</span>
					<span class="text-sm text-slate-300">{{ fDate(connection.created_at) }}</span>
				</div>
			</div>
		</div>

		<!-- Quick Actions -->
		<div class="bg-slate-800 border border-slate-600 rounded-lg p-4">
			<h4 class="text-sm font-semibold text-slate-300 mb-3">Quick Actions</h4>
			
			<div class="flex flex-wrap gap-2">
				<QBtn
					color="primary"
					size="sm"
					:loading="isVerifying"
					:disable="!connection.has_credentials"
					@click="verifyConnection"
				>
					<FaSolidShield class="w-3 h-3 mr-2" />
					Verify Connection
				</QBtn>

				<QBtn
					color="info"
					size="sm"
					:loading="isSyncing"
					:disable="!connection.is_connected"
					@click="syncMessages"
				>
					<FaSolidArrowsRotate class="w-3 h-3 mr-2" />
					Sync Messages
				</QBtn>

				<QBtn
					size="sm"
					color="secondary"
					@click="showMessages = !showMessages"
					:disable="!connection.is_connected"
				>
					<FaSolidComment class="w-3 h-3 mr-2" />
					{{ showMessages ? 'Hide' : 'Show' }} Messages
				</QBtn>

				<QBtn
					size="sm"
					color="secondary"
					@click="showTestMessage = !showTestMessage"
					:disable="!connection.is_connected"
				>
					<FaSolidPaperPlane class="w-3 h-3 mr-2" />
					Test Message
				</QBtn>
			</div>
		</div>

		<!-- Test Message Section -->
		<div v-if="showTestMessage && connection.is_connected" class="bg-slate-800 border border-slate-600 rounded-lg p-4">
			<h4 class="text-sm font-semibold text-slate-300 mb-3">Send Test Message</h4>
			
			<div class="flex flex-col gap-3">
				<TextField
					v-model="testMessage.phone_number"
					label="Phone Number"
					placeholder="+1234567890"
				/>

				<TextField
					v-model="testMessage.message"
					label="Message"
					placeholder="Hello from WhatsApp!"
					type="textarea"
					:rows="3"
				/>

				<div class="flex gap-2">
					<QBtn
						color="primary"
						size="sm"
						:loading="isTestingMessage"
						:disable="!testMessage.phone_number || !testMessage.message"
						@click="sendTestMessage"
					>
						<FaSolidPaperPlane class="w-3 h-3 mr-1" />
						Send Message
					</QBtn>
					
					<QBtn
						size="sm"
						color="secondary"
						@click="showTestMessage = false"
					>
						Cancel
					</QBtn>
				</div>
			</div>
		</div>

		<!-- Recent Messages -->
		<div v-if="showMessages && connection.is_connected" class="bg-slate-800 border border-slate-600 rounded-lg p-4">
			<div class="flex items-center justify-between mb-4">
				<h4 class="text-sm font-semibold text-slate-300">Recent Messages</h4>
				<QBtn
					size="sm"
					color="primary"
					:loading="loadingMessages"
					@click="loadRecentMessages"
				>
					<FaSolidArrowsRotate class="w-3 h-3 mr-1" />
					Refresh
				</QBtn>
			</div>

			<div v-if="loadingMessages" class="flex items-center justify-center py-8">
				<div class="w-6 h-6 border-2 border-blue-400 border-t-transparent rounded-full animate-spin"></div>
			</div>

			<div v-else-if="recentMessages.length === 0" class="text-center py-8 text-slate-400">
				No messages found
			</div>

			<div v-else class="space-y-3 max-h-96 overflow-y-auto">
				<div
					v-for="message in recentMessages"
					:key="message.id"
					class="flex gap-3 p-3 bg-slate-700 rounded-lg"
				>
					<div :class="message.is_inbound ? 'order-1' : 'order-2'" class="flex-1">
						<div class="flex items-center gap-2 mb-1">
							<span class="text-xs font-medium" :class="message.is_inbound ? 'text-blue-400' : 'text-green-400'">
								{{ message.is_inbound ? message.formatted_from_number : 'You' }}
							</span>
							<span class="text-xs text-slate-500">{{ fDate(message.created_at, 'h:mm A') }}</span>
							<div :class="getMessageStatusClass(message.status)"></div>
						</div>
						<p class="text-sm text-slate-300">{{ message.message }}</p>
						
						<div v-if="message.has_media" class="flex items-center gap-1 mt-2 text-xs text-slate-400">
							<FaSolidImage class="w-3 h-3" />
							<span>{{ message.media_urls?.length || 0 }} media file(s)</span>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import { TextField } from "quasar-ui-danx";
import { WhatsAppConnection, WhatsAppMessage } from "@/types/whatsapp";
import { FaSolidShield, FaSolidArrowsRotate, FaSolidComment, FaSolidPaperPlane, FaSolidImage } from "danx-icon";
import { computed, ref, onMounted } from "vue";
import { fDate, request } from "quasar-ui-danx";
import { Notify } from "quasar";

const props = defineProps<{
	connection: WhatsAppConnection;
}>();

const showMessages = ref(false);
const showTestMessage = ref(false);
const loadingMessages = ref(false);
const isTestingMessage = ref(false);
const recentMessages = ref<WhatsAppMessage[]>([]);

const testMessage = ref({
	phone_number: '',
	message: 'Hello from WhatsApp!'
});

const statusIndicatorClass = computed(() => {
	const baseClass = "w-3 h-3 rounded-full";
	
	switch (props.connection.status) {
		case 'connected':
			return `${baseClass} bg-green-400`;
		case 'pending':
			return `${baseClass} bg-yellow-400`;
		case 'error':
			return `${baseClass} bg-red-400`;
		default:
			return `${baseClass} bg-slate-400`;
	}
});

const statusBadgeClass = computed(() => {
	const baseClass = "font-medium";
	
	switch (props.connection.status) {
		case 'connected':
			return `${baseClass} bg-green-900 text-green-300`;
		case 'pending':
			return `${baseClass} bg-yellow-900 text-yellow-300`;
		case 'error':
			return `${baseClass} bg-red-900 text-red-300`;
		default:
			return `${baseClass} bg-slate-700 text-slate-300`;
	}
});

const providerBadgeClass = computed(() => {
	return props.connection.api_provider === 'twilio' 
		? 'bg-blue-900 text-blue-300 font-medium'
		: 'bg-green-900 text-green-300 font-medium';
});

// Action handlers
const isVerifying = ref(false);
const isSyncing = ref(false);

async function verifyConnection() {
	if (isVerifying.value) return;
	
	isVerifying.value = true;
	try {
		const result = await request.post(`/api/whatsapp-connections/${props.connection.id}/verify`);
		
		if (result.verified) {
			Notify.create({
				type: 'positive',
				message: 'Connection verified successfully!'
			});
		} else {
			Notify.create({
				type: 'negative', 
				message: 'Connection verification failed'
			});
		}
		
		// Update the connection status
		props.connection.status = result.status;
	} catch (error) {
		Notify.create({
			type: 'negative',
			message: 'Failed to verify connection'
		});
	} finally {
		isVerifying.value = false;
	}
}

async function syncMessages() {
	if (isSyncing.value) return;
	
	isSyncing.value = true;
	try {
		const result = await request.post(`/api/whatsapp-connections/${props.connection.id}/sync-messages`);
		
		props.connection.last_sync_at = result.last_sync_at;
		Notify.create({
			type: 'positive',
			message: 'Messages synced successfully!'
		});
		
		// Refresh messages if they're currently shown
		if (showMessages.value) {
			loadRecentMessages();
		}
	} catch (error) {
		Notify.create({
			type: 'negative',
			message: 'Failed to sync messages'
		});
	} finally {
		isSyncing.value = false;
	}
}

async function loadRecentMessages() {
	loadingMessages.value = true;
	
	try {
		const response = await request.get('/api/whatsapp-messages/recent', {
			connection_id: props.connection.id,
			limit: 20
		});
		
		recentMessages.value = response.data || [];
	} catch (error) {
		Notify.create({
			type: 'negative',
			message: 'Failed to load messages'
		});
	} finally {
		loadingMessages.value = false;
	}
}

async function sendTestMessage() {
	if (!testMessage.value.phone_number || !testMessage.value.message) {
		return;
	}

	isTestingMessage.value = true;

	try {
		const result = await request.post(`/api/whatsapp-connections/${props.connection.id}/test-message`, testMessage.value);
		
		if (result.success) {
			Notify.create({
				type: 'positive',
				message: 'Test message sent successfully!'
			});
			testMessage.value.phone_number = '';
			testMessage.value.message = 'Hello from WhatsApp!';
			showTestMessage.value = false;
			
			// Refresh messages if they're currently shown
			if (showMessages.value) {
				loadRecentMessages();
			}
		} else {
			throw new Error(result.error || 'Failed to send test message');
		}
	} catch (error) {
		Notify.create({
			type: 'negative',
			message: error.message || 'Failed to send test message'
		});
	} finally {
		isTestingMessage.value = false;
	}
}

function getMessageStatusClass(status: string): string {
	const baseClass = "w-2 h-2 rounded-full";
	
	switch (status) {
		case 'delivered':
			return `${baseClass} bg-green-400`;
		case 'read':
			return `${baseClass} bg-blue-400`;
		case 'sent':
			return `${baseClass} bg-yellow-400`;
		case 'failed':
			return `${baseClass} bg-red-400`;
		default:
			return `${baseClass} bg-slate-400`;
	}
}

// Load messages when the panel is shown for connected connections
onMounted(() => {
	if (props.connection.is_connected && showMessages.value) {
		loadRecentMessages();
	}
});
</script>