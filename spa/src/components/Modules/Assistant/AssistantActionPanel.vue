<template>
    <div class="assistant-action-panel bg-gray-50">
        <!-- Header -->
        <div class="panel-header bg-gray-100 px-4 py-2 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <FaSolidGears class="w-4 h-4 text-gray-600" />
                    <span class="text-sm font-medium text-gray-700">
                        AI Actions
                    </span>
                    <div
                        v-if="activeActionsCount > 0"
                        class="bg-blue-500 text-white text-xs px-2 py-1 rounded-full"
                    >
                        {{ activeActionsCount }}
                    </div>
                </div>
                <button
                    v-if="isCollapsible"
                    class="text-gray-500 hover:text-gray-700 transition-colors"
                    @click="toggleCollapse"
                >
                    <FaSolidChevronUp v-if="!isCollapsed" class="w-3 h-3" />
                    <FaSolidChevronDown v-else class="w-3 h-3" />
                </button>
            </div>
        </div>

        <!-- Actions List -->
        <div
            v-show="!isCollapsed"
            class="actions-list max-h-60 overflow-y-auto"
        >
            <div
                v-if="!actions.length"
                class="no-actions text-center py-8 text-gray-500"
            >
                <FaSolidWandMagicSparkles class="w-8 h-8 mx-auto mb-2 opacity-50" />
                <div class="text-sm">
                    No active actions. I'll suggest actions as we chat!
                </div>
            </div>

            <div
                v-for="action in sortedActions"
                :key="action.id"
                class="action-item"
            >
                <AssistantActionBox
                    :action="action"
                    @approve="handleApprove"
                    @cancel="handleCancel"
                    @preview="handlePreview"
                />
            </div>
        </div>

        <!-- Action Preview Modal -->
        <AssistantActionPreview
            v-if="previewAction"
            :action="previewAction"
            :visible="showPreview"
            @approve="handlePreviewApprove"
            @cancel="handlePreviewCancel"
            @close="closePreview"
        />
    </div>
</template>

<script setup lang="ts">
import { computed, ref } from "vue";
import {
    FaSolidChevronDown,
    FaSolidChevronUp,
    FaSolidGears,
    FaSolidWandMagicSparkles,
} from "danx-icon";
import AssistantActionBox from "./AssistantActionBox.vue";
import AssistantActionPreview from "./AssistantActionPreview.vue";
import { AssistantAction } from "./types";

// Props
interface Props {
    actions: AssistantAction[];
    threadId?: number;
    collapsible?: boolean;
    defaultCollapsed?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    actions: () => [],
    threadId: undefined,
    collapsible: true,
    defaultCollapsed: false,
});

// Emits
interface Emits {
    (e: 'action-approved', action: AssistantAction): void;
    (e: 'action-cancelled', action: AssistantAction): void;
    (e: 'action-previewed', action: AssistantAction): void;
}

const emit = defineEmits<Emits>();

// State
const isCollapsed = ref(props.defaultCollapsed);
const showPreview = ref(false);
const previewAction = ref<AssistantAction | null>(null);

// Computed properties
const isCollapsible = computed(() => props.collapsible);

const activeActionsCount = computed(() => {
    return props.actions.filter(action => 
        action.is_pending || action.is_in_progress
    ).length;
});

const sortedActions = computed(() => {
    // Sort by status priority and then by creation time
    return [...props.actions].sort((a, b) => {
        // Status priority: in_progress > pending > failed > completed > cancelled
        const statusPriority = {
            in_progress: 5,
            pending: 4,
            failed: 3,
            completed: 2,
            cancelled: 1,
        };
        
        const aPriority = statusPriority[a.status as keyof typeof statusPriority] || 0;
        const bPriority = statusPriority[b.status as keyof typeof statusPriority] || 0;
        
        if (aPriority !== bPriority) {
            return bPriority - aPriority;
        }
        
        // If same status, sort by creation time (newest first)
        return new Date(b.created_at).getTime() - new Date(a.created_at).getTime();
    });
});

// Methods
function toggleCollapse(): void {
    isCollapsed.value = !isCollapsed.value;
}

function handleApprove(action: AssistantAction): void {
    emit('action-approved', action);
}

function handleCancel(action: AssistantAction): void {
    emit('action-cancelled', action);
}

function handlePreview(action: AssistantAction): void {
    previewAction.value = action;
    showPreview.value = true;
    emit('action-previewed', action);
}

function handlePreviewApprove(): void {
    if (previewAction.value) {
        emit('action-approved', previewAction.value);
        closePreview();
    }
}

function handlePreviewCancel(): void {
    if (previewAction.value) {
        emit('action-cancelled', previewAction.value);
        closePreview();
    }
}

function closePreview(): void {
    showPreview.value = false;
    previewAction.value = null;
}
</script>

<style lang="scss" scoped>
.assistant-action-panel {
    .actions-list {
        &::-webkit-scrollbar {
            width: 4px;
        }
        
        &::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        &::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 2px;
            
            &:hover {
                background: #a1a1a1;
            }
        }
    }

    .action-item {
        animation: slideIn 0.3s ease-out;
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-10px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
    }

    .panel-header {
        transition: all 0.2s ease;
        
        &:hover {
            background-color: #f3f4f6;
        }
    }
}
</style>