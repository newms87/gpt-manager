<template>
    <div class="bg-slate-800 rounded p-3 space-y-3">
        <!-- Header Pills -->
        <div class="flex items-center gap-2 flex-wrap">
            <LabelPillWidget
                v-if="responseData?.status"
                :label="responseData.status"
                :color="statusColor"
                size="xs"
            />
            <div v-if="responseData?.usage">
                <LabelPillWidget
                    :label="tokenUsageSummary"
                    color="blue"
                    size="xs"
                    class="cursor-pointer"
                />
                <QPopupProxy>
                    <div class="bg-slate-700 p-3 rounded">
                        <div class="text-sm font-bold mb-2 text-slate-200">Token Usage Breakdown</div>
                        <div class="space-y-1 text-sm text-slate-300">
                            <div>Input tokens: {{ fNumber(responseData.usage.input_tokens) }}</div>
                            <div v-if="responseData.usage.input_tokens_details?.cached_tokens">
                                Cached tokens: {{ fNumber(responseData.usage.input_tokens_details.cached_tokens) }}
                            </div>
                            <div>Output tokens: {{ fNumber(responseData.usage.output_tokens) }}</div>
                            <div v-if="responseData.usage.output_tokens_details?.reasoning_tokens">
                                Reasoning tokens: {{ fNumber(responseData.usage.output_tokens_details.reasoning_tokens) }}
                            </div>
                            <div class="font-bold mt-2 pt-2 border-t border-slate-600">
                                Total tokens: {{ fNumber(responseData.usage.total_tokens) }}
                            </div>
                        </div>
                    </div>
                </QPopupProxy>
            </div>
        </div>

        <!-- Error Section -->
        <div v-if="responseData?.error_type || responseData?.error_message" class="bg-red-900/20 border border-red-700 rounded p-3 space-y-2">
            <div class="flex items-center gap-2">
                <LabelPillWidget
                    v-if="responseData.error_type"
                    :label="responseData.error_type"
                    color="red"
                    size="xs"
                />
            </div>
            <div v-if="responseData.error_message" class="text-sm text-red-200">
                <div class="font-bold mb-1">Error Message:</div>
                <div class="bg-slate-900 rounded p-2 whitespace-pre-wrap break-words">
                    {{ responseData.error_message }}
                </div>
            </div>
        </div>

        <!-- Output Items -->
        <div v-if="responseData?.output?.length">
            <div class="text-sm font-bold mb-2">Output</div>
            <ListTransition>
                <div
                    v-for="(outputItem, index) in responseData.output"
                    :key="index"
                    class="bg-slate-900 rounded p-2 mb-2"
                >
                    <!-- Reasoning Output -->
                    <div v-if="outputItem.type === 'reasoning'" class="text-sm">
                        <div class="flex items-center gap-2 mb-2">
                            <LabelPillWidget label="Reasoning" color="amber" size="xs" />
                        </div>
                        <div v-if="outputItem.summary?.length" class="text-slate-300">
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
                                    <MarkdownEditor
                                        :model-value="content.text"
                                        :format="isJSON(content.text) ? 'yaml' : 'text'"
                                        readonly
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
import MarkdownEditor from "@/components/MarkdownEditor/MarkdownEditor";
import {
    fNumber,
    isJSON,
    LabelPillWidget,
    ListTransition
} from "quasar-ui-danx";
import { computed } from "vue";

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

const tokenUsageSummary = computed(() => {
    if (!props.responseData?.usage) return "";
    const { input_tokens, output_tokens } = props.responseData.usage;
    return `${fNumber(input_tokens)} in / ${fNumber(output_tokens)} out`;
});
</script>
