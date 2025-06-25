<template>
    <div class="chat-error-message">
        <!-- Error Summary (always visible) -->
        <div class="error-summary">
            <div class="text-sm text-red-800">
                {{ errorSummary }}
            </div>
            
            <!-- Toggle button for details -->
            <button
                v-if="hasDetails"
                @click="showDetails = !showDetails"
                class="mt-1 text-xs text-red-600 hover:text-red-800 underline focus:outline-none"
            >
                {{ showDetails ? 'Hide details' : 'Show details' }}
            </button>
        </div>

        <!-- Error Details (expandable) -->
        <div
            v-if="showDetails && hasDetails"
            class="error-details mt-3 p-3 bg-red-100 rounded border border-red-200"
        >
            <!-- Full Error Message -->
            <div v-if="error.message && error.message !== errorSummary" class="mb-2">
                <div class="text-xs font-medium text-red-700 mb-1">Full Message:</div>
                <div class="text-xs text-red-800 font-mono whitespace-pre-wrap">{{ error.message }}</div>
            </div>

            <!-- Exception Type -->
            <div v-if="error.exception" class="mb-2">
                <div class="text-xs font-medium text-red-700 mb-1">Exception:</div>
                <div class="text-xs text-red-800 font-mono">{{ error.exception }}</div>
            </div>

            <!-- File and Line -->
            <div v-if="error.file || error.line" class="mb-2">
                <div class="text-xs font-medium text-red-700 mb-1">Location:</div>
                <div class="text-xs text-red-800 font-mono">
                    {{ error.file }}{{ error.line ? `:${error.line}` : '' }}
                </div>
            </div>

            <!-- Request Details -->
            <div v-if="error.url || error.status" class="mb-2">
                <div class="text-xs font-medium text-red-700 mb-1">Request:</div>
                <div class="text-xs text-red-800 font-mono">
                    {{ error.status ? `${error.status} ` : '' }}{{ error.url || 'Unknown endpoint' }}
                </div>
            </div>

            <!-- Additional Data -->
            <div v-if="error.data" class="mb-2">
                <div class="text-xs font-medium text-red-700 mb-1">Additional Info:</div>
                <pre class="text-xs text-red-800 bg-red-50 p-2 rounded border max-h-32 overflow-y-auto">{{ formatData(error.data) }}</pre>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';

interface Props {
    error: any;
}

const props = defineProps<Props>();

const showDetails = ref(false);

const errorSummary = computed(() => {
    if (typeof props.error === 'string') {
        return props.error;
    }
    
    if (props.error?.message) {
        // Truncate long messages for summary
        const message = props.error.message;
        return message.length > 100 ? message.substring(0, 100) + '...' : message;
    }
    
    return 'An unexpected error occurred';
});

const hasDetails = computed(() => {
    if (typeof props.error === 'string') {
        return false;
    }
    
    // Check if there are additional details beyond just the message
    return !!(props.error?.exception || 
              props.error?.file || 
              props.error?.line || 
              props.error?.url || 
              props.error?.status || 
              props.error?.data ||
              (props.error?.message && props.error.message.length > 100));
});

function formatData(data: any): string {
    if (typeof data === 'string') {
        return data;
    }
    
    try {
        return JSON.stringify(data, null, 2);
    } catch {
        return String(data);
    }
}
</script>

<style lang="scss" scoped>
.chat-error-message {
    .error-details {
        animation: slideDown 0.2s ease-out;
        
        @keyframes slideDown {
            from {
                opacity: 0;
                max-height: 0;
            }
            to {
                opacity: 1;
                max-height: 300px;
            }
        }
    }
}
</style>