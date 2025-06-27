<template>
    <div class="assistant-action-box border-b border-gray-200 last:border-b-0">
        <div class="action-content p-4">
            <!-- Action Header -->
            <div class="action-header flex items-center justify-between mb-2">
                <div class="flex items-center space-x-2">
                    <div
                        class="status-indicator w-3 h-3 rounded-full"
                        :class="statusIndicatorClass"
                    />
                    <span class="action-title font-medium text-sm text-gray-900">
                        {{ formatActionType(action.action_type) }}
                    </span>
                </div>
                <div class="action-status">
                    <span
                        class="status-badge px-2 py-1 text-xs rounded-full"
                        :class="statusBadgeClass"
                    >
                        {{ statusText }}
                    </span>
                </div>
            </div>

            <!-- Action Description -->
            <div
                v-if="action.description"
                class="action-description text-sm text-gray-600 mb-3"
            >
                {{ action.description }}
            </div>

            <!-- Action Progress -->
            <div
                v-if="action.is_in_progress"
                class="action-progress mb-3"
            >
                <div class="flex items-center space-x-2 mb-1">
                    <FaSolidSpinner class="w-3 h-3 text-blue-500 animate-spin" />
                    <span class="text-xs text-gray-600">In progress...</span>
                </div>
                <div class="progress-bar bg-gray-200 rounded-full h-1">
                    <div class="progress-fill bg-blue-500 h-1 rounded-full transition-all duration-300 animate-pulse w-1/2" />
                </div>
            </div>

            <!-- Action Results -->
            <div
                v-if="action.is_completed && action.result_data"
                class="action-results bg-green-50 border border-green-200 rounded-lg p-3 mb-3"
            >
                <div class="flex items-center space-x-2 mb-2">
                    <FaSolidCheck class="w-4 h-4 text-green-600" />
                    <span class="text-sm font-medium text-green-800">
                        Action Completed
                    </span>
                </div>
                <div class="text-xs text-green-700">
                    {{ formatResultMessage() }}
                </div>
            </div>

            <!-- Action Error -->
            <div
                v-if="action.is_failed && action.error_message"
                class="action-error bg-red-50 border border-red-200 rounded-lg p-3 mb-3"
            >
                <div class="flex items-center space-x-2 mb-2">
                    <FaSolidTriangleExclamation class="w-4 h-4 text-red-600" />
                    <span class="text-sm font-medium text-red-800">
                        Action Failed
                    </span>
                </div>
                <div class="text-xs text-red-700">
                    {{ action.error_message }}
                </div>
            </div>

            <!-- Action Preview Data -->
            <div
                v-if="action.preview_data && action.is_pending"
                class="action-preview bg-blue-50 border border-blue-200 rounded-lg p-3 mb-3"
            >
                <div class="text-xs text-blue-800 mb-2 font-medium">
                    Preview Changes:
                </div>
                <div class="preview-content text-xs text-blue-700">
                    {{ formatPreviewData() }}
                </div>
            </div>

            <!-- Action Buttons -->
            <div
                v-if="showActionButtons"
                class="action-buttons flex items-center justify-end space-x-2"
            >
                <button
                    v-if="action.is_pending"
                    class="preview-button bg-blue-600 hover:bg-blue-700 text-white px-4 py-1.5 rounded-lg text-sm font-medium transition-colors"
                    @click="handlePreview"
                >
                    Review & Approve
                </button>
            </div>

            <!-- Action Timing -->
            <div
                v-if="action.duration || action.started_at"
                class="action-timing text-xs text-gray-500 mt-2 text-right"
            >
                <span v-if="action.duration">
                    Took {{ action.duration }}s
                </span>
                <span v-else-if="action.started_at">
                    Started {{ formatTime(action.started_at) }}
                </span>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from "vue";
import { fDate } from "quasar-ui-danx";
import {
    FaSolidCheck,
    FaSolidTriangleExclamation,
    FaSolidSpinner,
} from "danx-icon";
import { AssistantAction } from "./types";
import { useAssistantChat } from "@/composables/useAssistantChat";

// Get action methods from composable
const { approveAction, cancelAction } = useAssistantChat();

// Props
interface Props {
    action: AssistantAction;
}

const props = defineProps<Props>();

// Emits (only preview now)
interface Emits {
    (e: 'preview', action: AssistantAction): void;
}

const emit = defineEmits<Emits>();

// Computed properties
const statusIndicatorClass = computed(() => {
    const classes = {
        pending: 'bg-yellow-400',
        in_progress: 'bg-blue-500 animate-pulse',
        completed: 'bg-green-500',
        failed: 'bg-red-500',
        cancelled: 'bg-gray-500',
    };
    
    return classes[props.action.status as keyof typeof classes] || 'bg-gray-400';
});

const statusBadgeClass = computed(() => {
    const classes = {
        pending: 'bg-yellow-100 text-yellow-800',
        in_progress: 'bg-blue-100 text-blue-800',
        completed: 'bg-green-100 text-green-800',
        failed: 'bg-red-100 text-red-800',
        cancelled: 'bg-gray-100 text-gray-800',
    };
    
    return classes[props.action.status as keyof typeof classes] || 'bg-gray-100 text-gray-800';
});

const statusText = computed(() => {
    const texts = {
        pending: 'Pending',
        in_progress: 'Running',
        completed: 'Done',
        failed: 'Failed',
        cancelled: 'Cancelled',
    };
    
    return texts[props.action.status as keyof typeof texts] || 'Unknown';
});

const showActionButtons = computed(() => {
    return props.action.is_pending || props.action.is_in_progress;
});

// Methods
async function handleApprove(): Promise<void> {
    await approveAction(props.action.id);
}

async function handleCancel(): Promise<void> {
    await cancelAction(props.action.id);
}

function handlePreview(): void {
    emit('preview', props.action);
}

function formatTime(timestamp: string): string {
    return fDate(timestamp, 'h:mm A');
}

function formatPreviewData(): string {
    if (!props.action.preview_data) return '';
    
    try {
        if (typeof props.action.preview_data === 'object') {
            // Try to format the preview data nicely
            if (props.action.preview_data.modification_type) {
                const { modification_type, target_path, reason } = props.action.preview_data;
                return `${modification_type} at ${target_path}: ${reason}`;
            }
            
            return JSON.stringify(props.action.preview_data, null, 2);
        }
        
        return String(props.action.preview_data);
    } catch (error) {
        return 'Preview data available';
    }
}

function formatResultMessage(): string {
    if (!props.action.result_data) return 'Action completed successfully';
    
    try {
        if (typeof props.action.result_data === 'object') {
            if (props.action.result_data.message) {
                return props.action.result_data.message;
            }
            
            if (props.action.result_data.schema_id) {
                return `Schema ${props.action.result_data.schema_id} has been updated`;
            }
            
            return 'Action completed with results';
        }
        
        return String(props.action.result_data);
    } catch (error) {
        return 'Action completed successfully';
    }
}

function formatActionType(actionType: string): string {
    // Convert snake_case action types to human-readable titles
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
</script>

<style lang="scss" scoped>
.assistant-action-box {
    transition: all 0.2s ease;
    
    &:hover {
        background-color: #fafafa;
    }

    .action-content {
        position: relative;
    }

    .status-indicator {
        flex-shrink: 0;
    }

    .action-title {
        line-height: 1.2;
    }

    .action-description {
        line-height: 1.4;
    }

    .preview-content {
        font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
        white-space: pre-wrap;
        word-break: break-word;
        max-height: 100px;
        overflow-y: auto;
        
        &::-webkit-scrollbar {
            width: 4px;
        }
        
        &::-webkit-scrollbar-track {
            background: rgba(59, 130, 246, 0.1);
        }
        
        &::-webkit-scrollbar-thumb {
            background: rgba(59, 130, 246, 0.3);
            border-radius: 2px;
        }
    }

    .action-buttons {
        button {
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
            
            &:hover {
                transform: translateY(-1px);
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }
            
            &:active {
                transform: translateY(0);
            }
        }
    }

    .progress-fill {
        animation: progressPulse 2s ease-in-out infinite;
        
        @keyframes progressPulse {
            0%, 100% { width: 30%; }
            50% { width: 70%; }
        }
    }
}
</style>