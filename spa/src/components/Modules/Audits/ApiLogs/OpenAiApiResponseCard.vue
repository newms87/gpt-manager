<template>
    <div :class="themeClass('bg-slate-800', 'bg-slate-100 border border-slate-200')" class="rounded p-3 space-y-3">
        <!-- Header Pills -->
        <div class="flex items-center gap-2 flex-wrap">
            <LabelPillWidget
                v-if="responseData?.status"
                :label="responseData.status"
                :color="statusColorThemed"
                size="xs"
            />
            <div v-if="responseData?.usage">
                <LabelPillWidget
                    :label="tokenUsageSummary"
                    :color="isDark ? 'blue' : 'blue-soft'"
                    size="xs"
                    class="cursor-pointer"
                />
                <QPopupProxy>
                    <div :class="themeClass('bg-slate-700', 'bg-white border border-slate-200 shadow-lg')" class="p-3 rounded">
                        <div :class="themeClass('text-slate-200', 'text-slate-700')" class="text-sm font-bold mb-2">Token Usage Breakdown</div>
                        <div :class="themeClass('text-slate-300', 'text-slate-600')" class="space-y-1 text-sm">
                            <div>Input tokens: {{ fNumber(responseData.usage.input_tokens) }}</div>
                            <div v-if="responseData.usage.input_tokens_details?.cached_tokens">
                                Cached tokens: {{ fNumber(responseData.usage.input_tokens_details.cached_tokens) }}
                            </div>
                            <div>Output tokens: {{ fNumber(responseData.usage.output_tokens) }}</div>
                            <div v-if="responseData.usage.output_tokens_details?.reasoning_tokens">
                                Reasoning tokens: {{ fNumber(responseData.usage.output_tokens_details.reasoning_tokens) }}
                            </div>
                            <div :class="themeClass('border-slate-600', 'border-slate-200')" class="font-bold mt-2 pt-2 border-t">
                                Total tokens: {{ fNumber(responseData.usage.total_tokens) }}
                            </div>
                        </div>
                    </div>
                </QPopupProxy>
            </div>
        </div>

        <!-- Error Section -->
        <div v-if="responseData?.error_type || responseData?.error_message" :class="themeClass('bg-red-900/20 border-red-700', 'bg-red-50 border-red-200')" class="border rounded p-3 space-y-2">
            <div class="flex items-center gap-2">
                <LabelPillWidget
                    v-if="responseData.error_type"
                    :label="responseData.error_type"
                    :color="isDark ? 'red' : 'red-soft'"
                    size="xs"
                />
            </div>
            <div v-if="responseData.error_message" :class="themeClass('text-red-200', 'text-red-700')" class="text-sm">
                <div class="font-bold mb-1">Error Message:</div>
                <div :class="themeClass('bg-slate-900', 'bg-white border border-red-200')" class="rounded p-2 whitespace-pre-wrap break-words">
                    {{ responseData.error_message }}
                </div>
            </div>
        </div>

        <!-- Output Items -->
        <div v-if="responseData?.output?.length">
            <div :class="themeClass('text-slate-200', 'text-slate-700')" class="text-sm font-bold mb-2">Output</div>
            <ListTransition>
                <div
                    v-for="(outputItem, index) in responseData.output"
                    :key="index"
                    :class="themeClass('bg-slate-900', 'bg-white border border-slate-200')"
                    class="rounded p-2 mb-2"
                >
                    <!-- Reasoning Output -->
                    <div v-if="outputItem.type === 'reasoning'" class="text-sm">
                        <div class="flex items-center gap-2 mb-2">
                            <LabelPillWidget label="Reasoning" :color="isDark ? 'amber' : 'amber-soft'" size="xs" />
                        </div>
                        <div v-if="outputItem.summary?.length" :class="themeClass('text-slate-300', 'text-slate-600')">
                            <div v-for="(summary, summaryIndex) in outputItem.summary" :key="summaryIndex">
                                {{ summary }}
                            </div>
                        </div>
                    </div>
                    <!-- Message Output -->
                    <div v-if="outputItem.type === 'message'" class="text-sm">
                        <div v-if="outputItem.content" class="space-y-2">
                            <template v-for="(content, contentIndex) in outputItem.content" :key="contentIndex">
                                <div v-if="content.type === 'output_text'">
                                    <CodeViewer
                                        :model-value="content.text"
                                        :format="isJSON(content.text) ? 'yaml' : 'markdown'"
                                        :theme="isDark ? 'dark' : 'light'"
                                        default-code-format="yaml"
                                    />
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </ListTransition>
        </div>
    </div>
</template>

<script setup lang="ts">
import { useAuditCardTheme } from "@/composables/useAuditCardTheme";
import {
    CodeViewer,
    fNumber,
    isJSON,
    LabelPillWidget,
    ListTransition
} from "quasar-ui-danx";
import { computed } from "vue";

const { isDark, themeClass } = useAuditCardTheme();

const props = defineProps<{
    responseData: any
}>();

// Computed values
const statusColor = computed(() => {
    switch (props.responseData?.status) {
        case "completed":
            return "green";
        case "failed":
            return "red";
        default:
            return "slate";
    }
});

const statusColorThemed = computed(() => {
    const baseColor = statusColor.value;
    return isDark.value ? baseColor : `${baseColor}-soft`;
});

const tokenUsageSummary = computed(() => {
    if (!props.responseData?.usage) return "";
    const { input_tokens, output_tokens } = props.responseData.usage;
    return `${fNumber(input_tokens)} in / ${fNumber(output_tokens)} out`;
});
</script>
