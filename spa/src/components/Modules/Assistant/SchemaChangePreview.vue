<template>
    <div class="schema-change-preview">
        <!-- Modification Summary -->
        <div class="modification-summary bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
            <div class="flex items-center space-x-2 mb-2">
                <FaSolidPenToSquare class="w-4 h-4 text-blue-600" />
                <span class="font-medium text-blue-900">
                    {{ formatModificationType() }}
                </span>
                <span
                    v-if="previewData.target_path"
                    class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-sm font-mono"
                >
                    {{ previewData.target_path }}
                </span>
            </div>
            <p
                v-if="previewData.reason"
                class="text-sm text-blue-800"
            >
                <strong>Reason:</strong> {{ previewData.reason }}
            </p>
        </div>

        <!-- Schema Diff -->
        <div class="schema-diff">
            <!-- Before/After Comparison -->
            <div
                v-if="hasBeforeAfter"
                class="before-after-comparison grid grid-cols-1 lg:grid-cols-2 gap-4"
            >
                <!-- Before -->
                <div class="before-schema">
                    <h4 class="text-sm font-medium text-gray-700 mb-2 flex items-center space-x-2">
                        <FaSolidMinus class="w-3 h-3 text-red-500" />
                        <span>Before</span>
                    </h4>
                    <div class="schema-content bg-red-50 border border-red-200 rounded p-3">
                        <pre class="text-sm text-red-800 overflow-auto max-h-64"><code>{{ formatBeforeSchema() }}</code></pre>
                    </div>
                </div>

                <!-- After -->
                <div class="after-schema">
                    <h4 class="text-sm font-medium text-gray-700 mb-2 flex items-center space-x-2">
                        <FaSolidPlus class="w-3 h-3 text-green-500" />
                        <span>After</span>
                    </h4>
                    <div class="schema-content bg-green-50 border border-green-200 rounded p-3">
                        <pre class="text-sm text-green-800 overflow-auto max-h-64"><code>{{ formatAfterSchema() }}</code></pre>
                    </div>
                </div>
            </div>

            <!-- Single Change View -->
            <div
                v-else
                class="single-change"
            >
                <h4 class="text-sm font-medium text-gray-700 mb-2">
                    Change Details
                </h4>
                <div class="change-content bg-gray-50 border border-gray-200 rounded p-3">
                    <pre class="text-sm text-gray-800 overflow-auto max-h-64"><code>{{ formatChangeDetails() }}</code></pre>
                </div>
            </div>
        </div>

        <!-- Impact Analysis -->
        <div
            v-if="impactAnalysis.length"
            class="impact-analysis mt-4 bg-yellow-50 border border-yellow-200 rounded-lg p-4"
        >
            <h4 class="text-sm font-medium text-yellow-800 mb-2 flex items-center space-x-2">
                <FaSolidCircleExclamation class="w-4 h-4" />
                <span>Potential Impact</span>
            </h4>
            <ul class="text-sm text-yellow-700 space-y-1">
                <li
                    v-for="impact in impactAnalysis"
                    :key="impact"
                    class="flex items-start space-x-2"
                >
                    <span class="w-1 h-1 bg-yellow-600 rounded-full mt-2 flex-shrink-0" />
                    <span>{{ impact }}</span>
                </li>
            </ul>
        </div>

        <!-- Property Details -->
        <div
            v-if="propertyDetails"
            class="property-details mt-4"
        >
            <h4 class="text-sm font-medium text-gray-700 mb-2">
                Property Information
            </h4>
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div
                    v-if="propertyDetails.type"
                    class="detail-item"
                >
                    <span class="text-gray-500">Type:</span>
                    <span class="font-medium">{{ propertyDetails.type }}</span>
                </div>
                <div
                    v-if="propertyDetails.required !== undefined"
                    class="detail-item"
                >
                    <span class="text-gray-500">Required:</span>
                    <span class="font-medium">{{ propertyDetails.required ? 'Yes' : 'No' }}</span>
                </div>
                <div
                    v-if="propertyDetails.format"
                    class="detail-item"
                >
                    <span class="text-gray-500">Format:</span>
                    <span class="font-medium">{{ propertyDetails.format }}</span>
                </div>
                <div
                    v-if="propertyDetails.description"
                    class="detail-item col-span-2"
                >
                    <span class="text-gray-500">Description:</span>
                    <span class="font-medium">{{ propertyDetails.description }}</span>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from "vue";
import {
    FaSolidPenToSquare,
    FaSolidCircleExclamation,
    FaSolidMinus,
    FaSolidPlus,
} from "danx-icon";

// Props
interface Props {
    previewData: any;
    actionType: string;
}

const props = defineProps<Props>();

// Computed properties
const hasBeforeAfter = computed(() => {
    return props.previewData.before_schema && props.previewData.after_schema;
});

const propertyDetails = computed(() => {
    if (props.previewData.modification_data?.definition) {
        return props.previewData.modification_data.definition;
    }
    
    if (props.previewData.modification_data?.type) {
        return props.previewData.modification_data;
    }
    
    return null;
});

const impactAnalysis = computed(() => {
    const impacts: string[] = [];
    const modificationType = props.previewData.modification_type;
    
    switch (modificationType) {
        case 'add_property':
            impacts.push('New property will be available for validation');
            if (propertyDetails.value?.required) {
                impacts.push('Required property may cause validation errors for existing data');
            }
            break;
            
        case 'modify_property':
            impacts.push('Property validation rules will change');
            impacts.push('Existing data may need to be validated against new rules');
            break;
            
        case 'remove_property':
            impacts.push('Property will no longer be validated');
            impacts.push('Existing data with this property will still be valid');
            break;
            
        case 'restructure':
            impacts.push('Schema structure will change significantly');
            impacts.push('Existing integrations may need updates');
            break;
    }
    
    return impacts;
});

// Methods
function formatModificationType(): string {
    const modificationType = props.previewData.modification_type || props.actionType;
    
    return modificationType
        .split('_')
        .map((word: string) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

function formatBeforeSchema(): string {
    if (!props.previewData.before_schema) return 'No before state';
    
    try {
        if (typeof props.previewData.before_schema === 'object') {
            return JSON.stringify(props.previewData.before_schema, null, 2);
        }
        return String(props.previewData.before_schema);
    } catch (error) {
        return 'Error formatting before schema';
    }
}

function formatAfterSchema(): string {
    if (!props.previewData.after_schema) return 'No after state';
    
    try {
        if (typeof props.previewData.after_schema === 'object') {
            return JSON.stringify(props.previewData.after_schema, null, 2);
        }
        return String(props.previewData.after_schema);
    } catch (error) {
        return 'Error formatting after schema';
    }
}

function formatChangeDetails(): string {
    const details = { ...props.previewData };
    
    // Remove redundant fields for cleaner display
    delete details.before_schema;
    delete details.after_schema;
    
    try {
        return JSON.stringify(details, null, 2);
    } catch (error) {
        return 'Error formatting change details';
    }
}
</script>

<style lang="scss" scoped>
.schema-change-preview {
    .schema-content {
        pre {
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: 0.8rem;
            line-height: 1.4;
            margin: 0;
            
            &::-webkit-scrollbar {
                width: 6px;
                height: 6px;
            }
            
            &::-webkit-scrollbar-track {
                background: rgba(0, 0, 0, 0.1);
                border-radius: 3px;
            }
            
            &::-webkit-scrollbar-thumb {
                background: rgba(0, 0, 0, 0.3);
                border-radius: 3px;
                
                &:hover {
                    background: rgba(0, 0, 0, 0.5);
                }
            }
        }
    }

    .before-after-comparison {
        @media (max-width: 1024px) {
            grid-template-columns: 1fr;
        }
    }

    .detail-item {
        display: flex;
        flex-direction: column;
        space-y: 0.25rem;
        
        span:first-child {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
    }

    .modification-summary {
        animation: slideIn 0.3s ease-out;
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    }
}
</style>