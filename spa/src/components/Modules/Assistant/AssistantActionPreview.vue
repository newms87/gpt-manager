<template>
    <ConfirmDialog
        v-if="visible"
        :title="formatActionType(action.action_type)"
        color="blue"
        :hide-cancel="true"
        :hide-ok="true"
        content-class="!max-w-4xl"
        @close="handleClose"
    >
        <div class="action-preview-container">
            <!-- Action Description Section -->
            <div class="bg-blue-50 rounded-lg p-6 mb-6">
                <div class="flex items-start space-x-3">
                    <div class="flex-shrink-0">
                        <div :class="['w-10 h-10 rounded-full flex items-center justify-center', actionIconBg]">
                            <component :is="actionIcon" class="w-5 h-5 text-white" />
                        </div>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">
                            {{ action.description || 'Action Details' }}
                        </h3>
                        <div v-if="action.target_type" class="text-sm text-gray-600">
                            <span class="font-medium">Target:</span> 
                            {{ formatTargetType(action.target_type) }}
                            <span v-if="action.target_id" class="ml-1">
                                (ID: {{ action.target_id }})
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Preview Content Section -->
            <div class="bg-white rounded-lg border border-gray-200 mb-6 overflow-hidden">
                <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                    <h4 class="text-base font-medium text-gray-900">
                        Preview Changes
                    </h4>
                </div>
                
                <div class="preview-content">
                    <!-- Schema Editor for schema-related actions -->
                    <div v-if="isSchemaAction" class="p-4">
                        <div class="mb-4 text-sm text-gray-600">
                            The following schema will be {{ actionVerb }}:
                        </div>
                        <div class="border border-gray-200 rounded-lg overflow-hidden">
                            <JSONSchemaEditor
                                v-if="schemaContent"
                                :model-value="schemaContent"
                                :readonly="true"
                                :show-toolbar="false"
                                class="h-96"
                            />
                            <div v-else class="p-8 text-center text-gray-500">
                                No schema preview available
                            </div>
                        </div>
                    </div>

                    <!-- Markdown Editor for content changes -->
                    <div v-else-if="isContentAction" class="p-4">
                        <div class="mb-4 text-sm text-gray-600">
                            The following content will be {{ actionVerb }}:
                        </div>
                        <div class="border border-gray-200 rounded-lg overflow-hidden">
                            <MarkdownEditor
                                v-if="contentPreview"
                                :model-value="contentPreview"
                                :readonly="true"
                                :show-toolbar="false"
                                class="h-96"
                            />
                            <div v-else class="p-8 text-center text-gray-500">
                                No content preview available
                            </div>
                        </div>
                    </div>

                    <!-- Generic JSON Preview -->
                    <div v-else class="p-4">
                        <div v-if="action.preview_data" class="bg-gray-900 rounded-lg p-4 overflow-auto max-h-96">
                            <pre class="text-sm text-gray-100"><code>{{ JSON.stringify(action.preview_data, null, 2) }}</code></pre>
                        </div>
                        <div v-else class="text-center text-gray-500 py-8">
                            No preview data available
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <button
                        class="px-6 py-2.5 bg-red-600 hover:bg-red-700 text-white font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                        @click="handleReject"
                    >
                        <FaSolidXmark class="w-4 h-4 inline mr-2" />
                        Reject & Cancel
                    </button>
                    <button
                        class="px-6 py-2.5 bg-yellow-600 hover:bg-yellow-700 text-white font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2"
                        @click="handleRequestChange"
                    >
                        <FaSolidPencil class="w-4 h-4 inline mr-2" />
                        Request Changes
                    </button>
                </div>
                
                <button
                    class="px-8 py-2.5 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2"
                    :disabled="isProcessing"
                    @click="handleApprove"
                >
                    <FaSolidSpinner v-if="isProcessing" class="w-4 h-4 inline mr-2 animate-spin" />
                    <FaSolidCheck v-else class="w-4 h-4 inline mr-2" />
                    Approve & Execute
                </button>
            </div>
        </div>
    </ConfirmDialog>

    <!-- Request Changes Dialog -->
    <ConfirmDialog
        v-if="showRequestChangeDialog"
        title="Request Changes"
        color="yellow"
        confirm-text="Send Request"
        @confirm="handleSendChangeRequest"
        @close="showRequestChangeDialog = false"
    >
        <div class="space-y-4">
            <p class="text-gray-600">
                Please describe what changes you'd like to make to this action:
            </p>
            <textarea
                v-model="changeRequest"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent"
                rows="4"
                placeholder="Describe the changes you'd like..."
            />
        </div>
    </ConfirmDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { ConfirmDialog } from "quasar-ui-danx";
import {
    FaSolidCheck,
    FaSolidXmark,
    FaSolidPencil,
    FaSolidSpinner,
    FaSolidWandMagicSparkles,
    FaSolidCode,
    FaSolidFileLines,
    FaSolidPlus,
    FaSolidPenToSquare,
    FaSolidTrash,
} from "danx-icon";
import JSONSchemaEditor from "@/components/Modules/SchemaEditor/JSONSchemaEditor.vue";
import MarkdownEditor from "@/components/MarkdownEditor/MarkdownEditor.vue";
import { AssistantAction } from "./types";
import { useAssistantChat } from "@/composables/useAssistantChat";
import { notify } from "quasar-ui-danx";

// Props
interface Props {
    action: AssistantAction;
    visible?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    visible: false,
});

// Emits
interface Emits {
    (e: 'close'): void;
}

const emit = defineEmits<Emits>();

// State
const isProcessing = ref(false);
const showRequestChangeDialog = ref(false);
const changeRequest = ref('');

// Get methods from composable
const { approveAction, cancelAction, sendMessage } = useAssistantChat();

// Computed properties
const isSchemaAction = computed(() => {
    return props.action.context === 'schema-editor' || 
           props.action.action_type.includes('schema') ||
           props.action.target_type === 'schema';
});

const isContentAction = computed(() => {
    return props.action.action_type.includes('content') ||
           props.action.action_type.includes('text') ||
           props.action.action_type.includes('document');
});

const actionVerb = computed(() => {
    const action = props.action.action_type.toLowerCase();
    if (action.includes('create') || action.includes('add')) return 'created';
    if (action.includes('modify') || action.includes('update') || action.includes('edit')) return 'modified';
    if (action.includes('delete') || action.includes('remove')) return 'deleted';
    return 'processed';
});

const actionIcon = computed(() => {
    const action = props.action.action_type.toLowerCase();
    if (action.includes('create') || action.includes('add')) return FaSolidPlus;
    if (action.includes('modify') || action.includes('update') || action.includes('edit')) return FaSolidPenToSquare;
    if (action.includes('delete') || action.includes('remove')) return FaSolidTrash;
    if (action.includes('schema')) return FaSolidCode;
    if (action.includes('content')) return FaSolidFileLines;
    return FaSolidWandMagicSparkles;
});

const actionIconBg = computed(() => {
    const action = props.action.action_type.toLowerCase();
    if (action.includes('create') || action.includes('add')) return 'bg-green-600';
    if (action.includes('modify') || action.includes('update') || action.includes('edit')) return 'bg-blue-600';
    if (action.includes('delete') || action.includes('remove')) return 'bg-red-600';
    return 'bg-purple-600';
});

const schemaContent = computed(() => {
    if (!props.action.preview_data) return null;
    
    // Check for after_schema in preview data
    if (props.action.preview_data.after_schema) {
        return JSON.stringify(props.action.preview_data.after_schema, null, 2);
    }
    
    // Check for schema in payload
    if (props.action.payload?.schema) {
        return JSON.stringify(props.action.payload.schema, null, 2);
    }
    
    // Check if preview_data itself is a schema
    if (props.action.preview_data.properties || props.action.preview_data.type) {
        return JSON.stringify(props.action.preview_data, null, 2);
    }
    
    return null;
});

const contentPreview = computed(() => {
    if (!props.action.preview_data) return null;
    
    // Check for content fields
    if (props.action.preview_data.content) {
        return props.action.preview_data.content;
    }
    
    if (props.action.preview_data.text) {
        return props.action.preview_data.text;
    }
    
    if (props.action.preview_data.markdown) {
        return props.action.preview_data.markdown;
    }
    
    // Check payload for content
    if (props.action.payload?.content) {
        return props.action.payload.content;
    }
    
    return null;
});

// Methods
async function handleApprove(): Promise<void> {
    isProcessing.value = true;
    try {
        await approveAction(props.action);
        notify.success('Action approved and executing');
        handleClose();
    } catch (error) {
        notify.error('Failed to approve action');
    } finally {
        isProcessing.value = false;
    }
}

async function handleReject(): Promise<void> {
    isProcessing.value = true;
    try {
        await cancelAction(props.action);
        notify.info('Action cancelled');
        handleClose();
    } catch (error) {
        notify.error('Failed to cancel action');
    } finally {
        isProcessing.value = false;
    }
}

function handleRequestChange(): void {
    showRequestChangeDialog.value = true;
}

async function handleSendChangeRequest(): Promise<void> {
    if (!changeRequest.value.trim()) {
        notify.warning('Please describe the changes you want');
        return;
    }
    
    // Cancel the current action
    await cancelAction(props.action);
    
    // Send a new message with the change request
    const message = `Please modify the previous action: ${changeRequest.value}`;
    await sendMessage(message);
    
    // Reset and close
    changeRequest.value = '';
    showRequestChangeDialog.value = false;
    handleClose();
}


function handleClose(): void {
    emit('close');
}

function formatActionType(actionType: string): string {
    const typeMap: Record<string, string> = {
        'create_schema': 'Create Schema',
        'modify_schema': 'Modify Schema', 
        'delete_schema': 'Delete Schema',
        'add_property': 'Add Property',
        'remove_property': 'Remove Property',
        'modify_property': 'Modify Property',
        'create_workflow': 'Create Workflow',
        'modify_workflow': 'Modify Workflow',
        'create_agent': 'Create Agent',
        'modify_agent': 'Modify Agent',
        'execute_task': 'Execute Task',
        'run_workflow': 'Run Workflow',
    };
    
    return typeMap[actionType] || actionType.split('_').map(word => 
        word.charAt(0).toUpperCase() + word.slice(1)
    ).join(' ');
}

function formatTargetType(targetType: string): string {
    return targetType.split('_').map(word => 
        word.charAt(0).toUpperCase() + word.slice(1)
    ).join(' ');
}
</script>

<style lang="scss" scoped>
.action-preview-container {
    min-height: 400px;
}

.preview-content {
    :deep(.schema-editor),
    :deep(.markdown-editor) {
        border: none !important;
        
        .editor-container {
            border: none !important;
        }
    }
}

pre {
    margin: 0;
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', 'Courier New', monospace;
    font-size: 0.875rem;
    line-height: 1.5;
    
    &::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }
    
    &::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.1);
        border-radius: 4px;
    }
    
    &::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.3);
        border-radius: 4px;
        
        &:hover {
            background: rgba(255, 255, 255, 0.5);
        }
    }
}
</style>