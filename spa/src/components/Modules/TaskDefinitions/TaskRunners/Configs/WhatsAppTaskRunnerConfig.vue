<template>
	<BaseTaskRunnerConfig :task-definition="taskDefinition">
		<QSeparator class="bg-slate-400 my-4" />

		<div class="flex flex-col gap-6">
			<!-- Action Selection -->
			<div>
				<SelectField
					:model-value="taskDefinition.task_runner_config?.action || 'send_message'"
					label="Action"
					:options="actionOptions"
					@update:model-value="updateAction"
				/>
				<div class="text-xs text-slate-400 mt-1">
					Choose what WhatsApp action to perform.
				</div>
			</div>

			<!-- Connection Selection -->
			<div>
				<SelectField
					:model-value="taskDefinition.task_runner_config?.connection_id"
					label="WhatsApp Connection"
					:options="connectionOptions"
					:loading="loadingConnections"
					@update:model-value="updateConnectionId"
				/>
				<div class="text-xs text-slate-400 mt-1">
					Select the WhatsApp connection to use for this task.
				</div>
				
				<div v-if="connectionOptions.length === 0 && !loadingConnections" class="mt-2">
					<div class="text-xs text-yellow-400 mb-2">
						No WhatsApp connections found. Create one first.
					</div>
					<QBtn
						size="sm"
						color="primary"
						@click="openConnectionManager"
					>
						<FaSolidPlus class="w-3 h-3 mr-1" />
						Manage Connections
					</QBtn>
				</div>
			</div>

			<!-- Send Message Configuration -->
			<div v-if="selectedAction === 'send_message'" class="border border-slate-600 rounded-lg p-4">
				<div class="flex items-center gap-2 mb-4">
					<FaSolidPaperPlane class="w-4 h-4 text-green-400" />
					<h3 class="text-sm font-semibold text-slate-300">Send Message Configuration</h3>
				</div>

				<div class="flex flex-col gap-4">
					<TextField
						:model-value="taskDefinition.task_runner_config?.phone_number || ''"
						label="Target Phone Number"
						placeholder="+1234567890"
						@update:model-value="updatePhoneNumber"
					/>

					<div>
						<MarkdownEditor
							:model-value="taskDefinition.task_runner_config?.message_template || ''"
							label="Message Template"
							placeholder="Hello! Your artifact {artifact.name} is ready."
							editor-class="min-h-[120px]"
							@update:model-value="updateMessageTemplate"
						/>
						<div class="text-xs text-slate-400 mt-1">
							Use placeholders like {artifact.name}, {artifact.text}, {artifact.id}, or {artifact.key} for JSON fields.
						</div>
					</div>
				</div>
			</div>

			<!-- Process Messages Configuration -->
			<div v-if="selectedAction === 'process_messages'" class="border border-slate-600 rounded-lg p-4">
				<div class="flex items-center gap-2 mb-4">
					<FaSolidFilter class="w-4 h-4 text-blue-400" />
					<h3 class="text-sm font-semibold text-slate-300">Message Processing Configuration</h3>
				</div>

				<div class="flex flex-col gap-4">
					<TextField
						:model-value="taskDefinition.task_runner_config?.filter_phone_number || ''"
						label="Filter by Phone Number (optional)"
						placeholder="+1234567890"
						@update:model-value="updateFilterPhoneNumber"
					/>

					<div class="flex gap-4">
						<QCheckbox
							:model-value="taskDefinition.task_runner_config?.include_inbound !== false"
							label="Include Inbound Messages"
							@update:model-value="updateIncludeInbound"
						/>

						<QCheckbox
							:model-value="taskDefinition.task_runner_config?.include_outbound !== true"
							label="Include Outbound Messages" 
							@update:model-value="updateIncludeOutbound"
						/>
					</div>
				</div>
			</div>

			<!-- Preview Section -->
			<div class="bg-slate-700 border border-slate-600 rounded-lg p-4">
				<div class="flex items-center gap-2 mb-2">
					<FaSolidCircleInfo class="w-4 h-4 text-blue-400" />
					<span class="text-sm font-medium text-slate-300">Configuration Summary</span>
				</div>
				
				<div class="text-xs text-slate-400 space-y-1">
					<div><strong>Action:</strong> {{ actionLabels[selectedAction] }}</div>
					<div v-if="selectedConnection">
						<strong>Connection:</strong> {{ selectedConnection.name }} ({{ selectedConnection.display_phone_number }})
					</div>
					<div v-if="selectedAction === 'send_message' && taskDefinition.task_runner_config?.phone_number">
						<strong>Target:</strong> {{ taskDefinition.task_runner_config.phone_number }}
					</div>
					<div v-if="selectedAction === 'process_messages' && taskDefinition.task_runner_config?.filter_phone_number">
						<strong>Filter:</strong> {{ taskDefinition.task_runner_config.filter_phone_number }}
					</div>
				</div>
			</div>

			<!-- Usage Examples -->
			<div class="bg-slate-700 border border-slate-600 rounded-lg p-4">
				<div class="flex items-center gap-2 mb-2">
					<FaSolidLightbulb class="w-4 h-4 text-yellow-400" />
					<span class="text-sm font-medium text-slate-300">Usage Examples</span>
				</div>
				
				<div class="text-xs text-slate-400 space-y-2">
					<div v-if="selectedAction === 'send_message'">
						<strong>Send Message:</strong> Send WhatsApp messages using artifact data
						<ul class="list-disc list-inside mt-1 space-y-1">
							<li>Notify customers about order status using artifact content</li>
							<li>Send personalized promotional messages</li>
							<li>Deliver reports or summaries generated by previous tasks</li>
						</ul>
					</div>
					
					<div v-if="selectedAction === 'sync_messages'">
						<strong>Sync Messages:</strong> Fetch recent messages from WhatsApp
						<ul class="list-disc list-inside mt-1 space-y-1">
							<li>Import new messages for processing</li>
							<li>Keep local message database up to date</li>
							<li>Prepare data for analysis tasks</li>
						</ul>
					</div>
					
					<div v-if="selectedAction === 'process_messages'">
						<strong>Process Messages:</strong> Convert WhatsApp messages into artifacts
						<ul class="list-disc list-inside mt-1 space-y-1">
							<li>Analyze customer inquiries and feedback</li>
							<li>Extract information from conversations</li>
							<li>Feed messages into AI analysis workflows</li>
						</ul>
					</div>
				</div>
			</div>
		</div>
	</BaseTaskRunnerConfig>
</template>

<script setup lang="ts">
import BaseTaskRunnerConfig from "./BaseTaskRunnerConfig.vue";
import MarkdownEditor from "@/components/MarkdownEditor/MarkdownEditor.vue";
import { TaskDefinition } from "@/types";
import { WhatsAppConnection } from "@/types/whatsapp";
import { TextField, SelectField } from "quasar-ui-danx";
import { FaSolidPaperPlane, FaSolidFilter, FaSolidCircleInfo, FaSolidLightbulb, FaSolidPlus } from "danx-icon";
import { computed, onMounted, ref } from "vue";
import { request } from "quasar-ui-danx";
import { useRouter } from "vue-router";

const props = defineProps<{
	taskDefinition: TaskDefinition;
}>();

const router = useRouter();
const loadingConnections = ref(false);
const connections = ref<WhatsAppConnection[]>([]);

const actionOptions = [
	{ label: 'Send Message', value: 'send_message' },
	{ label: 'Sync Messages', value: 'sync_messages' },
	{ label: 'Process Messages', value: 'process_messages' }
];

const actionLabels = {
	send_message: 'Send WhatsApp messages to specified recipients',
	sync_messages: 'Sync recent messages from WhatsApp API',
	process_messages: 'Convert WhatsApp messages into artifacts for processing'
};

const selectedAction = computed(() => {
	return props.taskDefinition.task_runner_config?.action || 'send_message';
});

const connectionOptions = computed(() => {
	return connections.value.map(connection => ({
		label: `${connection.name} (${connection.display_phone_number})`,
		value: connection.id
	}));
});

const selectedConnection = computed(() => {
	const connectionId = props.taskDefinition.task_runner_config?.connection_id;
	return connections.value.find(c => c.id === connectionId);
});

function updateConfig() {
	if (!props.taskDefinition.task_runner_config) {
		props.taskDefinition.task_runner_config = {};
	}
}

function updateAction(value: string) {
	updateConfig();
	props.taskDefinition.task_runner_config.action = value;
}

function updateConnectionId(value: number) {
	updateConfig();
	props.taskDefinition.task_runner_config.connection_id = value;
}

function updatePhoneNumber(value: string) {
	updateConfig();
	props.taskDefinition.task_runner_config.phone_number = value;
}

function updateMessageTemplate(value: string) {
	updateConfig();
	props.taskDefinition.task_runner_config.message_template = value;
}

function updateFilterPhoneNumber(value: string) {
	updateConfig();
	props.taskDefinition.task_runner_config.filter_phone_number = value;
}

function updateIncludeInbound(value: boolean) {
	updateConfig();
	props.taskDefinition.task_runner_config.include_inbound = value;
}

function updateIncludeOutbound(value: boolean) {
	updateConfig();
	props.taskDefinition.task_runner_config.include_outbound = value;
}

function openConnectionManager() {
	// Navigate to WhatsApp connection management
	const routeData = router.resolve({ name: 'whatsapp-connections' });
	window.open(routeData.href, '_blank');
}

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

onMounted(() => {
	loadConnections();
});
</script>