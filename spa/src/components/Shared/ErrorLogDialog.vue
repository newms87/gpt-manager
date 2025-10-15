<template>
    <InfoDialog
        :model-value="!!url"
        title="Error Log"
        max-width="900px"
        color="red"
        @close="$emit('close')"
    >
        <template #default>
            <div v-if="loading" class="flex items-center justify-center py-8">
                <QSpinnerGears class="text-red-500 w-12 h-12" />
                <p class="ml-3 text-slate-400">Loading errors...</p>
            </div>

            <div v-else-if="errors.length === 0" class="text-center py-8">
                <FaSolidCircleCheck class="w-12 h-12 text-green-500 mx-auto mb-3" />
                <p class="text-slate-600">No errors found</p>
            </div>

            <div v-else class="space-y-3 max-h-[600px] overflow-y-auto">
                <div
                    v-for="error in errors"
                    :key="error.id"
                    class="border border-red-200 rounded-lg p-4 bg-red-50"
                >
                    <div class="flex items-start gap-3">
                        <FaSolidTriangleExclamation class="w-5 h-5 text-red-600 flex-shrink-0 mt-1" />
                        <div class="flex-1 min-w-0">
                            <!-- Error Message -->
                            <div class="font-semibold text-red-900 mb-2">
                                {{ error.message }}
                            </div>

                            <!-- Error Details -->
                            <div class="text-sm space-y-1 mb-3">
                                <div v-if="error.error_class" class="text-slate-700">
                                    <span class="font-medium">Class:</span> {{ error.error_class }}
                                </div>
                                <div v-if="error.file" class="text-slate-700">
                                    <span class="font-medium">File:</span> {{ error.file }}:{{ error.line }}
                                </div>
                                <div class="text-slate-600">
                                    <span class="font-medium">Time:</span> {{ fDateTime(error.created_at) }}
                                </div>
                            </div>

                            <!-- Stack Trace (Expandable) -->
                            <div v-if="error.stack_trace && error.stack_trace.length > 0" class="mt-3">
                                <ActionButton
                                    type="chevron-right"
                                    :type="expandedErrors[error.id] ? 'chevron-down' : 'chevron-right'"
                                    label="Stack Trace"
                                    size="xs"
                                    color="slate"
                                    @click="toggleStackTrace(error.id)"
                                />
                                <div
                                    v-if="expandedErrors[error.id]"
                                    class="mt-2 bg-slate-900 text-green-400 p-3 rounded text-xs font-mono overflow-x-auto max-h-96 overflow-y-auto"
                                >
                                    <div
                                        v-for="(trace, index) in error.stack_trace"
                                        :key="index"
                                        class="mb-2 pb-2 border-b border-slate-700 last:border-0"
                                    >
                                        <div class="text-yellow-400">{{ trace.class }}{{ trace.type }}{{
                                                trace.function
                                            }}()
                                        </div>
                                        <div class="text-slate-400 text-xs">{{ trace.file }}:{{ trace.line }}</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Additional Data (Expandable) -->
                            <div v-if="error.data && Object.keys(error.data).length > 0" class="mt-2">
                                <ActionButton
                                    :type="expandedData[error.id] ? 'chevron-down' : 'chevron-right'"
                                    label="Additional Data"
                                    size="xs"
                                    color="slate"
                                    @click="toggleData(error.id)"
                                />
                                <div
                                    v-if="expandedData[error.id]"
                                    class="mt-2 bg-slate-100 p-3 rounded text-xs"
                                >
                                    <pre>{{ JSON.stringify(error.data, null, 2) }}</pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </InfoDialog>
</template>

<script setup lang="ts">
import { ErrorLogEntry } from "@/components/Modules/Audits/audit-requests";
import { FaSolidCircleCheck, FaSolidTriangleExclamation } from "danx-icon";
import { ActionButton, fDateTime, InfoDialog, request } from "quasar-ui-danx";
import { onMounted, reactive, ref } from "vue";

const props = defineProps<{
    url: string | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

const loading = ref(false);
const errors = ref<ErrorLogEntry[]>([]);
const expandedErrors = reactive<Record<number, boolean>>({});
const expandedData = reactive<Record<number, boolean>>({});

const toggleStackTrace = (errorId: number) => {
    expandedErrors[errorId] = !expandedErrors[errorId];
};

const toggleData = (errorId: number) => {
    expandedData[errorId] = !expandedData[errorId];
};

const loadErrors = async () => {
    if (!props.url) return;

    loading.value = true;
    try {
        const response = await request.get(props.url);
        errors.value = response || [];
    } catch (error) {
        console.error("Failed to load errors:", error);
        errors.value = [];
    } finally {
        loading.value = false;
    }
};

onMounted(loadErrors);
</script>
