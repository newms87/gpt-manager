<template>
	<div class="flex flex-col gap-6">
		<!-- Message Header -->
		<div class="bg-slate-800 border border-slate-600 rounded-lg p-4">
			<div class="flex items-center justify-between mb-4">
				<div class="flex items-center gap-3">
					<div :class="directionIndicatorClass"></div>
					<div>
						<h3 class="text-lg font-semibold text-slate-200">
							{{ message.is_inbound ? 'Received Message' : 'Sent Message' }}
						</h3>
						<p class="text-sm text-slate-400">{{ fDate(message.created_at) }}</p>
					</div>
				</div>
				
				<div class="flex items-center gap-2">
					<span class="text-xs px-2 py-1 rounded font-medium" :class="statusBadgeClass">
						{{ message.status.charAt(0).toUpperCase() + message.status.slice(1) }}
					</span>
				</div>
			</div>

			<div class="grid grid-cols-2 gap-4">
				<div>
					<span class="text-xs text-slate-500 block">From</span>
					<span class="text-sm text-slate-300">{{ message.formatted_from_number }}</span>
				</div>
				<div>
					<span class="text-xs text-slate-500 block">To</span>
					<span class="text-sm text-slate-300">{{ message.formatted_to_number }}</span>
				</div>
				<div v-if="message.external_id">
					<span class="text-xs text-slate-500 block">Message ID</span>
					<span class="text-xs text-slate-300 font-mono">{{ message.external_id }}</span>
				</div>
				<div>
					<span class="text-xs text-slate-500 block">Connection</span>
					<span class="text-sm text-slate-300">{{ message.whatsapp_connection?.name || 'Unknown' }}</span>
				</div>
			</div>
		</div>

		<!-- Message Content -->
		<div class="bg-slate-800 border border-slate-600 rounded-lg p-4">
			<h4 class="text-sm font-semibold text-slate-300 mb-3">Message Content</h4>
			
			<div class="bg-slate-900 rounded-lg p-4 border border-slate-700">
				<p class="text-slate-200 whitespace-pre-wrap">{{ message.message }}</p>
			</div>

			<!-- Media Attachments -->
			<div v-if="message.has_media && message.media_urls" class="mt-4">
				<h5 class="text-sm font-semibold text-slate-300 mb-2">Media Attachments</h5>
				<div class="space-y-2">
					<div
						v-for="(url, index) in message.media_urls"
						:key="index"
						class="flex items-center gap-2 p-2 bg-slate-700 rounded"
					>
						<FaSolidImage class="w-4 h-4 text-blue-400" />
						<a
							:href="url"
							target="_blank"
							class="text-sm text-blue-400 hover:text-blue-300 truncate"
						>
							{{ url }}
						</a>
					</div>
				</div>
			</div>
		</div>

		<!-- Message Status Timeline -->
		<div class="bg-slate-800 border border-slate-600 rounded-lg p-4">
			<h4 class="text-sm font-semibold text-slate-300 mb-3">Status Timeline</h4>
			
			<div class="space-y-3">
				<div class="flex items-center gap-3">
					<div class="w-2 h-2 bg-blue-400 rounded-full"></div>
					<div class="flex-1">
						<span class="text-sm text-slate-300">Message Created</span>
						<span class="text-xs text-slate-500 block">{{ fDate(message.created_at) }}</span>
					</div>
				</div>

				<div v-if="message.sent_at" class="flex items-center gap-3">
					<div class="w-2 h-2 bg-yellow-400 rounded-full"></div>
					<div class="flex-1">
						<span class="text-sm text-slate-300">Sent</span>
						<span class="text-xs text-slate-500 block">{{ fDate(message.sent_at) }}</span>
					</div>
				</div>

				<div v-if="message.delivered_at" class="flex items-center gap-3">
					<div class="w-2 h-2 bg-green-400 rounded-full"></div>
					<div class="flex-1">
						<span class="text-sm text-slate-300">Delivered</span>
						<span class="text-xs text-slate-500 block">{{ fDate(message.delivered_at) }}</span>
					</div>
				</div>

				<div v-if="message.read_at" class="flex items-center gap-3">
					<div class="w-2 h-2 bg-purple-400 rounded-full"></div>
					<div class="flex-1">
						<span class="text-sm text-slate-300">Read</span>
						<span class="text-xs text-slate-500 block">{{ fDate(message.read_at) }}</span>
					</div>
				</div>

				<div v-if="message.failed_at" class="flex items-center gap-3">
					<div class="w-2 h-2 bg-red-400 rounded-full"></div>
					<div class="flex-1">
						<span class="text-sm text-slate-300">Failed</span>
						<span class="text-xs text-slate-500 block">{{ fDate(message.failed_at) }}</span>
						<span v-if="message.error_message" class="text-xs text-red-400 block">
							{{ message.error_message }}
						</span>
					</div>
				</div>
			</div>
		</div>

		<!-- Metadata -->
		<div v-if="message.metadata" class="bg-slate-800 border border-slate-600 rounded-lg p-4">
			<h4 class="text-sm font-semibold text-slate-300 mb-3">Additional Information</h4>
			
			<div class="bg-slate-900 rounded border border-slate-700 p-3">
				<pre class="text-xs text-slate-400 whitespace-pre-wrap">{{ JSON.stringify(message.metadata, null, 2) }}</pre>
			</div>
		</div>

		<!-- Actions -->
		<div v-if="message.is_inbound && message.whatsapp_connection?.is_connected" class="bg-slate-800 border border-slate-600 rounded-lg p-4">
			<h4 class="text-sm font-semibold text-slate-300 mb-3">Quick Actions</h4>
			
			<div class="flex flex-col gap-3">
				<TextField
					v-model="replyMessage"
					label="Reply Message"
					placeholder="Type your reply..."
					type="textarea"
					:rows="3"
				/>

				<div class="flex gap-2">
					<QBtn
						color="primary"
						size="sm"
						:loading="sendingReply"
						:disable="!replyMessage.trim()"
						@click="sendReply"
					>
						<FaSolidReply class="w-3 h-3 mr-1" />
						Send Reply
					</QBtn>
					
					<QBtn
						size="sm"
						color="secondary"
						@click="replyMessage = ''"
					>
						Clear
					</QBtn>
				</div>
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import { TextField } from "quasar-ui-danx";
import { WhatsAppMessage } from "@/types/whatsapp";
import { FaSolidImage, FaSolidReply } from "danx-icon";
import { computed, ref } from "vue";
import { fDate, request } from "quasar-ui-danx";
import { Notify } from "quasar";

const props = defineProps<{
	message: WhatsAppMessage;
}>();

const replyMessage = ref('');
const sendingReply = ref(false);

const directionIndicatorClass = computed(() => {
	const baseClass = "w-3 h-3 rounded-full";
	
	return props.message.is_inbound 
		? `${baseClass} bg-blue-400`
		: `${baseClass} bg-green-400`;
});

const statusBadgeClass = computed(() => {
	const baseClass = "font-medium";
	
	switch (props.message.status) {
		case 'delivered':
		case 'read':
			return `${baseClass} bg-green-900 text-green-300`;
		case 'sent':
			return `${baseClass} bg-blue-900 text-blue-300`;
		case 'pending':
			return `${baseClass} bg-yellow-900 text-yellow-300`;
		case 'failed':
			return `${baseClass} bg-red-900 text-red-300`;
		default:
			return `${baseClass} bg-slate-700 text-slate-300`;
	}
});

async function sendReply() {
	if (!replyMessage.value.trim() || !props.message.whatsapp_connection) {
		return;
	}

	sendingReply.value = true;

	try {
		const response = await request.post(`/api/whatsapp-connections/${props.message.whatsapp_connection.id}/test-message`, {
			phone_number: props.message.from_number,
			message: replyMessage.value.trim()
		});

		if (response.success) {
			Notify.create({
				type: 'positive',
				message: 'Reply sent successfully!'
			});
			replyMessage.value = '';
		} else {
			throw new Error(response.error || 'Failed to send reply');
		}
	} catch (error) {
		Notify.create({
			type: 'negative',
			message: error.message || 'Failed to send reply'
		});
	} finally {
		sendingReply.value = false;
	}
}
</script>