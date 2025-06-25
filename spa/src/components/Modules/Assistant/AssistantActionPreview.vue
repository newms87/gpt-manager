<template>
    <FullScreenDialog
        v-model="isVisible"
        title="Action Preview"
        :loading="loading"
        @close="handleClose"
    >
        <template #default>
            <div class="action-preview-content p-6">
                <!-- Action Header -->
                <div class="action-header mb-6">
                    <div class="flex items-center space-x-3 mb-2">
                        <div
                            class="status-indicator w-4 h-4 rounded-full bg-yellow-400"
                        />
                        <h2 class="text-xl font-semibold text-gray-900">
                            {{ action.title }}
                        </h2>
                        <div class="action-type bg-blue-100 text-blue-800 px-2 py-1 rounded text-sm">
                            {{ formatActionType() }}
                        </div>
                    </div>
                    
                    <p
                        v-if="action.description"
                        class="text-gray-600 text-sm"
                    >
                        {{ action.description }}
                    </p>
                </div>

                <!-- Target Information -->
                <div
                    v-if="action.target_type"
                    class="target-info bg-gray-50 rounded-lg p-4 mb-6"
                >
                    <h3 class="text-lg font-medium text-gray-900 mb-2">
                        Target
                    </h3>
                    <div class="text-sm text-gray-600">
                        <div><strong>Type:</strong> {{ formatTargetType() }}</div>
                        <div v-if="action.target_id">
                            <strong>ID:</strong> {{ action.target_id }}
                        </div>
                    </div>
                </div>

                <!-- Changes Preview -->
                <div
                    v-if="action.preview_data"
                    class="changes-preview mb-6"
                >
                    <h3 class="text-lg font-medium text-gray-900 mb-4">
                        Proposed Changes
                    </h3>
                    
                    <!-- Schema Changes -->
                    <div
                        v-if="action.context === 'schema-editor'"
                        class="schema-changes"
                    >
                        <SchemaChangePreview
                            :preview-data="action.preview_data"
                            :action-type="action.action_type"
                        />
                    </div>
                    
                    <!-- Generic Changes -->
                    <div
                        v-else
                        class="generic-changes bg-white border border-gray-200 rounded-lg p-4"
                    >
                        <pre class="text-sm text-gray-700 whitespace-pre-wrap overflow-auto max-h-96"><code>{{ formatPreviewData() }}</code></pre>
                    </div>
                </div>

                <!-- Action Payload -->
                <div
                    v-if="action.payload && showAdvanced"
                    class="action-payload mb-6"
                >
                    <h3 class="text-lg font-medium text-gray-900 mb-4">
                        Technical Details
                    </h3>
                    <div class="bg-gray-900 text-gray-100 rounded-lg p-4 font-mono text-sm overflow-auto max-h-64">
                        <pre><code>{{ JSON.stringify(action.payload, null, 2) }}</code></pre>
                    </div>
                </div>

                <!-- Risk Assessment -->
                <div class="risk-assessment bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                    <div class="flex items-start space-x-3">
                        <FaSolidTriangleExclamation class="w-5 h-5 text-yellow-600 mt-0.5 flex-shrink-0" />
                        <div>
                            <h4 class="font-medium text-yellow-800 mb-2">
                                Review Changes Carefully
                            </h4>
                            <div class="text-sm text-yellow-700 space-y-1">
                                <div v-if="action.context === 'schema-editor'">
                                    • Schema changes may affect existing data validation
                                </div>
                                <div v-if="action.action_type.includes('delete')">
                                    • This action will permanently remove data
                                </div>
                                <div>
                                    • Changes cannot be automatically undone
                                </div>
                                <div>
                                    • Test in development before applying to production
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Toggle Advanced -->
                <div class="advanced-toggle mb-6">
                    <button
                        class="text-blue-600 hover:text-blue-800 text-sm font-medium"
                        @click="showAdvanced = !showAdvanced"
                    >
                        {{ showAdvanced ? 'Hide' : 'Show' }} Technical Details
                        <FaSolidChevronDown
                            v-if="!showAdvanced"
                            class="w-3 h-3 inline ml-1"
                        />
                        <FaSolidChevronUp
                            v-else
                            class="w-3 h-3 inline ml-1"
                        />
                    </button>
                </div>
            </div>
        </template>

        <template #actions>
            <div class="flex items-center justify-between w-full">
                <div class="flex items-center space-x-2">
                    <button
                        class="cancel-button bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium transition-colors"
                        @click="handleCancel"
                    >
                        Cancel Action
                    </button>
                </div>
                
                <div class="flex items-center space-x-2">
                    <button
                        class="close-button bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-lg font-medium transition-colors"
                        @click="handleClose"
                    >
                        Close Preview
                    </button>
                    <button
                        class="approve-button bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium transition-colors"
                        @click="handleApprove"
                    >
                        <FaSolidCheck class="w-4 h-4 inline mr-2" />
                        Approve & Execute
                    </button>
                </div>
            </div>
        </template>
    </FullScreenDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { FullScreenDialog } from "quasar-ui-danx";
import {
    FaSolidCheck,
    FaSolidChevronDown,
    FaSolidChevronUp,
    FaSolidTriangleExclamation,
} from "danx-icon";
import SchemaChangePreview from "./SchemaChangePreview.vue";
import { AssistantAction } from "./types";

// Props
interface Props {
    action: AssistantAction;
    visible?: boolean;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    visible: false,
    loading: false,
});

// Emits
interface Emits {
    (e: 'approve'): void;
    (e: 'cancel'): void;
    (e: 'close'): void;
}

const emit = defineEmits<Emits>();

// State
const isVisible = ref(props.visible);
const showAdvanced = ref(false);

// Watch for visibility changes
watch(
    () => props.visible,
    (newValue) => {
        isVisible.value = newValue;
    }
);

// Computed properties
const actionTypeColors = computed(() => {
    const colors = {
        create: 'bg-green-100 text-green-800',
        update: 'bg-blue-100 text-blue-800',
        modify: 'bg-blue-100 text-blue-800',
        delete: 'bg-red-100 text-red-800',
        remove: 'bg-red-100 text-red-800',
        validate: 'bg-purple-100 text-purple-800',
        generate: 'bg-yellow-100 text-yellow-800',
    };
    
    const actionType = props.action.action_type.toLowerCase();
    
    for (const [key, color] of Object.entries(colors)) {
        if (actionType.includes(key)) {
            return color;
        }
    }
    
    return 'bg-gray-100 text-gray-800';
});

// Methods
function handleApprove(): void {
    emit('approve');
}

function handleCancel(): void {
    emit('cancel');
}

function handleClose(): void {
    isVisible.value = false;
    emit('close');
}

function formatActionType(): string {
    return props.action.action_type
        .split('_')
        .map(word => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

function formatTargetType(): string {
    return props.action.target_type
        .split('_')
        .map(word => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

function formatPreviewData(): string {
    if (!props.action.preview_data) return 'No preview data available';
    
    try {
        if (typeof props.action.preview_data === 'object') {
            return JSON.stringify(props.action.preview_data, null, 2);
        }
        
        return String(props.action.preview_data);
    } catch (error) {
        return 'Error formatting preview data';
    }
}
</script>

<style lang="scss" scoped>
.action-preview-content {
    max-width: 4xl;
    margin: 0 auto;

    .status-indicator {
        flex-shrink: 0;
    }

    .changes-preview {
        pre {
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: 0.875rem;
            line-height: 1.5;
            
            &::-webkit-scrollbar {
                width: 8px;
                height: 8px;
            }
            
            &::-webkit-scrollbar-track {
                background: #f1f1f1;
                border-radius: 4px;
            }
            
            &::-webkit-scrollbar-thumb {
                background: #c1c1c1;
                border-radius: 4px;
                
                &:hover {
                    background: #a1a1a1;
                }
            }
        }
    }

    .action-payload {
        code {
            font-size: 0.8rem;
            line-height: 1.4;
        }
    }

    .risk-assessment {
        animation: fadeIn 0.3s ease-out;
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }
    }

    .advanced-toggle {
        border-top: 1px solid #e5e7eb;
        padding-top: 1rem;
    }
}

// Responsive adjustments
@media (max-width: 768px) {
    .action-preview-content {
        padding: 1rem;
        
        .changes-preview pre {
            font-size: 0.75rem;
        }
    }
}
</style>