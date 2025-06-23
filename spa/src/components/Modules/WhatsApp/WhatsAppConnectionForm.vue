<template>
	<div class="flex flex-col gap-6">
		<div class="flex flex-col gap-4">
			<TextField
				v-model="connection.name"
				label="Connection Name"
				placeholder="My WhatsApp Business"
				required
			/>

			<SelectField
				v-model="connection.api_provider"
				label="API Provider"
				:options="apiProviderOptions"
				required
			/>

			<TextField
				v-model="connection.phone_number"
				label="Phone Number"
				placeholder="+1234567890"
				required
			/>
		</div>

		<!-- Twilio Configuration -->
		<div v-if="connection.api_provider === 'twilio'" class="border border-slate-600 rounded-lg p-4">
			<div class="flex items-center gap-2 mb-4">
				<FaSolidKey class="w-4 h-4 text-blue-400" />
				<h3 class="text-sm font-semibold text-slate-300">Twilio Configuration</h3>
			</div>
			
			<div class="flex flex-col gap-4">
				<TextField
					v-model="connection.account_sid"
					label="Account SID"
					placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
					required
				/>

				<TextField
					v-model="connection.auth_token"
					label="Auth Token"
					type="password"
					placeholder="Your Twilio Auth Token"
					required
				/>
			</div>
		</div>

		<!-- WhatsApp Business Configuration -->
		<div v-if="connection.api_provider === 'whatsapp_business'" class="border border-slate-600 rounded-lg p-4">
			<div class="flex items-center gap-2 mb-4">
				<FaSolidKey class="w-4 h-4 text-green-400" />
				<h3 class="text-sm font-semibold text-slate-300">WhatsApp Business API Configuration</h3>
			</div>
			
			<div class="flex flex-col gap-4">
				<TextField
					v-model="connection.access_token"
					label="Access Token"
					type="password"
					placeholder="Your WhatsApp Business API Access Token"
					required
				/>

				<TextField
					v-model="apiConfig.phone_number_id"
					label="Phone Number ID"
					placeholder="123456789012345"
					required
				/>
			</div>
		</div>

		<!-- Status Display -->
		<div v-if="connection.id" class="border border-slate-600 rounded-lg p-4">
			<div class="flex items-center gap-2 mb-4">
				<FaSolidCircleInfo class="w-4 h-4 text-blue-400" />
				<h3 class="text-sm font-semibold text-slate-300">Connection Status</h3>
			</div>
			
			<div class="flex flex-col gap-3">
				<div class="flex items-center justify-between">
					<span class="text-sm text-slate-400">Status:</span>
					<div class="flex items-center gap-2">
						<div :class="statusIndicatorClass"></div>
						<span class="text-sm capitalize" :class="statusTextClass">{{ connection.status }}</span>
					</div>
				</div>

				<div v-if="connection.verified_at" class="flex items-center justify-between">
					<span class="text-sm text-slate-400">Verified:</span>
					<span class="text-sm text-slate-300">{{ fDate(connection.verified_at) }}</span>
				</div>

				<div v-if="connection.last_sync_at" class="flex items-center justify-between">
					<span class="text-sm text-slate-400">Last Sync:</span>
					<span class="text-sm text-slate-300">{{ fDate(connection.last_sync_at) }}</span>
				</div>

				<div class="flex items-center justify-between">
					<span class="text-sm text-slate-400">Messages:</span>
					<span class="text-sm text-slate-300">{{ connection.message_count || 0 }}</span>
				</div>
			</div>
		</div>

		<!-- Actions -->
		<div v-if="connection.id" class="flex flex-wrap gap-2">
			<QBtn
				color="primary"
				size="sm"
				:loading="isVerifying"
				:disable="!connection.has_credentials"
				@click="verifyConnection"
			>
				<FaSolidShield class="w-3 h-3 mr-1" />
				Verify Connection
			</QBtn>

			<QBtn
				color="secondary"
				size="sm"
				:loading="isGeneratingWebhook"
				@click="generateWebhookUrl"
			>
				<FaSolidLink class="w-3 h-3 mr-1" />
				Generate Webhook URL
			</QBtn>

			<QBtn
				color="info"
				size="sm"
				:loading="isSyncing"
				:disable="!connection.is_connected"
				@click="syncMessages"
			>
				<FaSolidArrowsRotate class="w-3 h-3 mr-1" />
				Sync Messages
			</QBtn>
		</div>

		<!-- Webhook URL Display -->
		<div v-if="connection.webhook_url" class="border border-slate-600 rounded-lg p-4">
			<div class="flex items-center gap-2 mb-3">
				<FaSolidLink class="w-4 h-4 text-blue-400" />
				<h3 class="text-sm font-semibold text-slate-300">Webhook URL</h3>
			</div>
			
			<div class="flex items-center gap-2">
				<input
					:value="connection.webhook_url"
					readonly
					class="flex-1 bg-slate-800 border border-slate-600 rounded px-3 py-2 text-xs text-slate-300 font-mono"
				/>
				<QBtn
					size="sm"
					color="secondary"
					@click="copyWebhookUrl"
				>
					<FaSolidCopy class="w-3 h-3" />
				</QBtn>
			</div>
			
			<div class="text-xs text-slate-400 mt-2">
				Configure this URL in your {{ connection.api_provider === 'twilio' ? 'Twilio' : 'WhatsApp Business' }} webhook settings.
			</div>
		</div>

		<!-- Test Message -->
		<div v-if="connection.is_connected" class="border border-slate-600 rounded-lg p-4">
			<div class="flex items-center gap-2 mb-4">
				<FaSolidPaperPlane class="w-4 h-4 text-green-400" />
				<h3 class="text-sm font-semibold text-slate-300">Test Message</h3>
			</div>
			
			<div class="flex flex-col gap-3">
				<TextField
					v-model="testMessage.phone_number"
					label="Test Phone Number"
					placeholder="+1234567890"
				/>

				<TextField
					v-model="testMessage.message"
					label="Test Message"
					placeholder="Hello from WhatsApp!"
					type="textarea"
					:rows="3"
				/>

				<QBtn
					color="primary"
					size="sm"
					:loading="isTestingMessage"
					:disable="!testMessage.phone_number || !testMessage.message"
					@click="sendTestMessage"
				>
					<FaSolidPaperPlane class="w-3 h-3 mr-1" />
					Send Test Message
				</QBtn>
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import { TextField, SelectField } from "quasar-ui-danx";
import { WhatsAppConnection } from "@/types/whatsapp";
import { FaSolidKey, FaSolidCircleInfo, FaSolidShield, FaSolidLink, FaSolidArrowsRotate, FaSolidCopy, FaSolidPaperPlane } from "danx-icon";
import { computed, ref, watch } from "vue";
import { fDate, request } from "quasar-ui-danx";
import { Notify } from "quasar";

const props = defineProps<{
	connection: WhatsAppConnection;
}>();

const testMessage = ref({
	phone_number: '',
	message: 'Hello from WhatsApp!'
});

const isTestingMessage = ref(false);

const apiProviderOptions = [
	{ label: 'Twilio', value: 'twilio' },
	{ label: 'WhatsApp Business API', value: 'whatsapp_business' }
];

const apiConfig = computed({
	get: () => props.connection.api_config || {},
	set: (value) => {
		props.connection.api_config = value;
	}
});

const statusIndicatorClass = computed(() => {
	const baseClass = "w-2 h-2 rounded-full";
	
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

const statusTextClass = computed(() => {
	switch (props.connection.status) {
		case 'connected':
			return 'text-green-400';
		case 'pending':
			return 'text-yellow-400';
		case 'error':
			return 'text-red-400';
		default:
			return 'text-slate-400';
	}
});

// Action handlers
const isVerifying = ref(false);
const isGeneratingWebhook = ref(false);
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

async function generateWebhookUrl() {
	if (isGeneratingWebhook.value) return;
	
	isGeneratingWebhook.value = true;
	try {
		const result = await request.post(`/api/whatsapp-connections/${props.connection.id}/generate-webhook-url`);
		
		props.connection.webhook_url = result.webhook_url;
		Notify.create({
			type: 'positive',
			message: 'Webhook URL generated successfully!'
		});
	} catch (error) {
		Notify.create({
			type: 'negative',
			message: 'Failed to generate webhook URL'
		});
	} finally {
		isGeneratingWebhook.value = false;
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
	} catch (error) {
		Notify.create({
			type: 'negative',
			message: 'Failed to sync messages'
		});
	} finally {
		isSyncing.value = false;
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

function copyWebhookUrl() {
	navigator.clipboard.writeText(props.connection.webhook_url);
	Notify.create({
		type: 'positive',
		message: 'Webhook URL copied to clipboard!'
	});
}

// Watch for api_provider changes to initialize api_config
watch(() => props.connection.api_provider, (provider) => {
	if (provider === 'whatsapp_business' && !props.connection.api_config) {
		props.connection.api_config = {};
	}
}, { immediate: true });
</script>