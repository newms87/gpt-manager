<template>
    <QCard class="bg-slate-800 p-4">
        <template v-if="error">
            <div class="text-red-300 bg-red-900 p-4 rounded">
                Failed to parse API log data: {{ error }}
            </div>
        </template>
        <template v-else>
            <!-- Header Row -->
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div class="flex items-center gap-2 flex-wrap">
                    <LabelPillWidget
                        v-if="requestData?.model"
                        :label="requestData.model"
                        color="purple"
                        size="xs"
                    />
                    <LabelPillWidget
                        v-if="responseData?.status"
                        :label="responseData.status"
                        :color="statusColor"
                        size="xs"
                    />
                    <LabelPillWidget
                        v-if="requestData?.reasoning?.effort"
                        :label="`Reasoning: ${requestData.reasoning.effort}`"
                        color="amber"
                        size="xs"
                    />
                    <LabelPillWidget
                        v-if="requestData?.service_tier"
                        :label="`Service: ${requestData.service_tier}`"
                        color="slate"
                        size="xs"
                    />
                </div>
                <div class="flex items-center gap-2">
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
                    <LabelPillWidget
                        :label="fMillisecondsToDuration(apiLog.run_time_ms)"
                        color="green"
                        size="xs"
                    />
                </div>
            </div>

            <QSeparator class="bg-slate-600 my-3" />

            <!-- Request Section -->
            <div class="mb-3">
                <div class="flex items-center justify-between mb-2">
                    <div class="font-bold text-lg">Request</div>
                    <ShowHideButton v-model="showRequest" label="Request Details" />
                </div>
                <div v-if="showRequest" class="bg-slate-700 rounded p-3 space-y-3">
                    <!-- Instructions -->
                    <div v-if="requestData?.instructions">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="text-sm font-bold">Instructions</div>
                            <ShowHideButton v-model="showInstructions" label="" size="xs" color="sky-invert" />
                        </div>
                        <div v-if="showInstructions" class="text-sm">
                            <div v-if="!showFullInstructions && instructionsPreview" class="mb-2">
                                {{ instructionsPreview }}
                                <QBtn
                                    flat
                                    dense
                                    label="Show more"
                                    class="text-sky-400 ml-2"
                                    @click="showFullInstructions = true"
                                />
                            </div>
                            <div v-else class="whitespace-pre-wrap">
                                {{ requestData.instructions }}
                            </div>
                        </div>
                    </div>

                    <!-- Input Messages -->
                    <div v-if="requestData?.input?.length">
                        <div class="flex items-center justify-between mb-2">
                            <div class="text-sm font-bold">Input Messages ({{ requestData.input.length }})</div>
                            <QBtn
                                flat
                                dense
                                :label="allMessagesExpanded ? 'Hide All' : 'Show All'"
                                class="text-sky-400 text-xs"
                                @click="toggleAllMessages"
                            />
                        </div>
                        <ListTransition>
                            <div
                                v-for="(inputItem, index) in requestData.input"
                                :key="index"
                                class="bg-slate-800 rounded p-2 mb-2"
                            >
                                <!-- Header row: Icon, Text toggle, Image toggle -->
                                <div class="flex items-center gap-2">
                                    <!-- Role Icon -->
                                    <div class="rounded-full p-1 w-6 h-6 flex items-center justify-center flex-shrink-0" :class="getRoleClass(inputItem.role)">
                                        <component :is="getRoleIcon(inputItem.role)" class="w-3 text-slate-300" />
                                    </div>

                                    <!-- Text toggle -->
                                    <ShowHideButton
                                        v-if="getMessageTexts(inputItem).length > 0"
                                        v-model="expandedMessageText[index]"
                                        :show-icon="TextIcon"
                                        :hide-icon="TextIcon"
                                        label=""
                                        size="xs"
                                        color="sky-invert"
                                    />

                                    <!-- Image toggle -->
                                    <ShowHideButton
                                        v-if="getMessageImages(inputItem).length > 0"
                                        v-model="expandedMessageFiles[index]"
                                        :show-icon="ImageIcon"
                                        :hide-icon="HideImageIcon"
                                        :label="getMessageImages(inputItem).length"
                                        size="xs"
                                        color="sky-invert"
                                    />

                                    <!-- Preview when both collapsed -->
                                    <div
                                        v-if="!expandedMessageText[index] && !expandedMessageFiles[index]"
                                        class="flex-grow min-w-0 flex items-center gap-1"
                                    >
                                        <!-- Small image thumbnails (up to 5) -->
                                        <template v-for="(imgUrl, imgIndex) in getMessageImages(inputItem).slice(0, 5)" :key="imgIndex">
                                            <FilePreview :src="imgUrl" class="w-6 h-6 flex-shrink-0 rounded" />
                                        </template>
                                        <span v-if="getMessageImages(inputItem).length > 5" class="text-xs text-slate-500 flex-shrink-0">
                                            +{{ getMessageImages(inputItem).length - 5 }}
                                        </span>
                                        <!-- Text preview -->
                                        <div class="text-sm text-slate-400 truncate">
                                            {{ getMessagePreview(inputItem) }}
                                        </div>
                                    </div>
                                </div>

                                <!-- Images section -->
                                <div v-if="expandedMessageFiles[index] && getMessageImages(inputItem).length > 0" class="mt-3 flex flex-wrap gap-2">
                                    <FilePreview
                                        v-for="(imgUrl, imgIndex) in getMessageImages(inputItem)"
                                        :key="imgIndex"
                                        :src="imgUrl"
                                        class="w-48 h-48 rounded cursor-pointer"
                                    />
                                </div>

                                <!-- Text section -->
                                <div v-if="expandedMessageText[index] && getMessageTexts(inputItem).length > 0" class="mt-3 space-y-2">
                                    <MarkdownEditor
                                        v-for="(text, textIndex) in getMessageTexts(inputItem)"
                                        :key="textIndex"
                                        :model-value="text"
                                        :format="isJSON(text) ? 'yaml' : 'text'"
                                        readonly
                                    />
                                </div>
                            </div>
                        </ListTransition>
                    </div>

                    <!-- Response Format (moved to last) -->
                    <div v-if="requestData?.text?.format">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="text-sm font-bold">Response Format</div>
                            <ShowHideButton v-model="showResponseFormat" label="" size="xs" color="sky-invert" />
                        </div>
                        <div v-if="showResponseFormat" class="bg-slate-800 rounded p-2">
                            <div class="text-xs mb-1">
                                Type: <span class="text-sky-300">{{ requestData.text.format.type }}</span>
                            </div>
                            <div v-if="requestData.text.format.name" class="text-xs mb-2">
                                Schema: <span class="text-sky-300">{{ requestData.text.format.name }}</span>
                            </div>
                            <MarkdownEditor
                                v-if="requestData.text.format.schema"
                                :model-value="JSON.stringify(requestData.text.format.schema, null, 2)"
                                format="yaml"
                                readonly
                            />
                        </div>
                    </div>
                </div>
            </div>

            <QSeparator class="bg-slate-600 my-3" />

            <!-- Response Section -->
            <div class="mb-3">
                <div class="flex items-center justify-between mb-2">
                    <div class="font-bold text-lg">Response</div>
                    <ShowHideButton v-model="showResponse" label="Response Details" />
                </div>
                <div v-if="showResponse" class="bg-slate-700 rounded p-3 space-y-3">
                    <!-- Output Items -->
                    <div v-if="responseData?.output?.length">
                        <div class="text-sm font-bold mb-2">Output</div>
                        <ListTransition>
                            <div
                                v-for="(outputItem, index) in responseData.output"
                                :key="index"
                                class="bg-slate-800 rounded p-2 mb-2"
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
            </div>

            <QSeparator class="bg-slate-600 my-3" />

            <!-- Raw API Log Toggle -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <div class="font-bold text-lg">Raw API Log</div>
                    <ShowHideButton v-model="showRawLog" label="Raw API Log" />
                </div>
                <div v-if="showRawLog">
                    <ApiLogEntryCard :api-log="apiLog" />
                </div>
            </div>
        </template>
    </QCard>
</template>

<script setup lang="ts">
import MarkdownEditor from "@/components/MarkdownEditor/MarkdownEditor";
import ApiLogEntryCard from "@/components/Modules/Audits/ApiLogs/ApiLogEntryCard.vue";
import { ApiLog } from "@/components/Modules/Audits/audit-requests";
import {
    FaRegularUser as UserIcon,
    FaSolidRobot as AssistantIcon,
    FaSolidImage as ImageIcon,
    FaRegularImage as HideImageIcon,
    FaSolidFileLines as TextIcon
} from "danx-icon";
import {
    fMillisecondsToDuration,
    fNumber,
    isJSON,
    LabelPillWidget,
    ListTransition,
    ShowHideButton,
    FilePreview
} from "quasar-ui-danx";
import { computed, ref, watch } from "vue";

const props = defineProps<{
    apiLog: ApiLog
}>();

// Toggle states
const showRequest = ref(true);
const showResponse = ref(true);
const showRawLog = ref(false);
const showInstructions = ref(true);
const showFullInstructions = ref(false);
const showResponseFormat = ref(false);
const expandedMessageFiles = ref<Record<number, boolean>>({});
const expandedMessageText = ref<Record<number, boolean>>({});

// Parse JSON safely - handle both string and object formats
const error = ref<string | null>(null);
const requestData = ref<any>(null);
const responseData = ref<any>(null);

function parseJsonSafely(data: string | object): object | null {
    if (typeof data === "object" && data !== null) {
        return data;
    }
    if (typeof data === "string") {
        try {
            return JSON.parse(data);
        } catch {
            return null;
        }
    }
    return null;
}

try {
    requestData.value = parseJsonSafely(props.apiLog.request);
    responseData.value = parseJsonSafely(props.apiLog.response);
    if (!requestData.value || !responseData.value) {
        error.value = "Unable to parse request or response data";
    }
} catch (e) {
    error.value = e instanceof Error ? e.message : "Unknown error parsing data";
}

// Computed values
const statusColor = computed(() => {
    switch (responseData.value?.status) {
        case "completed":
            return "green";
        case "failed":
            return "red";
        default:
            return "slate";
    }
});

const tokenUsageSummary = computed(() => {
    if (!responseData.value?.usage) return "";
    const { input_tokens, output_tokens } = responseData.value.usage;
    return `${fNumber(input_tokens)} in / ${fNumber(output_tokens)} out`;
});

const instructionsPreview = computed(() => {
    if (!requestData.value?.instructions) return "";
    const instructions = requestData.value.instructions;
    if (instructions.length <= 200) {
        showFullInstructions.value = true;
        return "";
    }
    return instructions.substring(0, 200) + "...";
});

function getRoleColor(role: string): string {
    switch (role) {
        case "user":
            return "lime-700";
        case "assistant":
            return "sky-600";
        default:
            return "slate";
    }
}

function getRoleIcon(role: string) {
    return role === 'assistant' ? AssistantIcon : UserIcon;
}

function getRoleClass(role: string) {
    return role === 'assistant' ? 'bg-sky-600' : 'bg-lime-700';
}

function getMessagePreview(inputItem: any): string {
    const textContent = inputItem.content?.find((c: any) => c.type === 'input_text');
    if (!textContent?.text) return '';
    return textContent.text.length > 100 ? textContent.text.substring(0, 100) + '...' : textContent.text;
}

function getMessageImages(inputItem: any): string[] {
    return inputItem.content
        ?.filter((c: any) => c.type === 'input_image')
        ?.map((c: any) => typeof c.image_url === 'string' ? c.image_url : c.image_url?.url)
        ?.filter(Boolean) || [];
}

function getMessageTexts(inputItem: any): string[] {
    return inputItem.content
        ?.filter((c: any) => c.type === 'input_text')
        ?.map((c: any) => c.text)
        ?.filter(Boolean) || [];
}

// Input messages toggle helpers
const allMessagesExpanded = computed(() => {
    const messageCount = requestData.value?.input?.length || 0;
    if (messageCount === 0) return false;

    for (let i = 0; i < messageCount; i++) {
        const inputItem = requestData.value.input[i];
        const hasText = getMessageTexts(inputItem).length > 0;
        const hasImages = getMessageImages(inputItem).length > 0;

        if (hasText && !expandedMessageText.value[i]) return false;
        if (hasImages && !expandedMessageFiles.value[i]) return false;
    }
    return true;
});

function toggleAllMessages() {
    const messageCount = requestData.value?.input?.length || 0;
    const shouldExpand = !allMessagesExpanded.value;

    for (let i = 0; i < messageCount; i++) {
        expandedMessageText.value[i] = shouldExpand;
        expandedMessageFiles.value[i] = shouldExpand;
    }
}
</script>
