<template>
    <QCard :class="themeClass('bg-slate-700', 'bg-white shadow border border-slate-200')" class="p-3 overflow-hidden min-w-0">
        <div class="flex-x overflow-hidden">
            <div class="flex-x space-x-3 flex-grow">
                <LabelPillWidget :label="'api-log: ' + apiLog.id" :color="isDark ? 'sky' : 'sky-soft'" size="xs" />
                <div class="rounded-2xl px-3 py-1" :class="methodClass">{{ apiLog.method }}</div>
                <ApiStatusCodeBadge :status-code="apiLog.status_code" />
            </div>
            <div class="flex-x flex-shrink-0 space-x-2">
                <ElapsedTimer
                    :start-time="apiLog.started_at"
                    :end-time="apiLog.finished_at"
                />
                <LabelPillWidget :color="isDark ? 'slate' : 'slate-soft'" size="sm">
                    {{ fDateTime(apiLog.created_at) }}
                    <QTooltip class="text-base">{{ fDateTimeMs(apiLog.created_at) }}</QTooltip>
                </LabelPillWidget>
            </div>
        </div>
        <div :class="themeClass('text-slate-200', 'text-slate-800')" class="mt-5 font-semibold text-base text-no-wrap overflow-hidden overflow-ellipsis max-w-full">
            {{ decodedUrl }}
            <QTooltip>{{ decodedUrl }}</QTooltip>
        </div>

        <!-- Headers Section -->
        <div :class="themeClass('border-slate-600', 'border-slate-200')" class="mt-4 border-t py-2">
            <div class="flex items-center gap-2">
                <div :class="themeClass('text-slate-300', 'text-slate-600')" class="font-semibold">
                    Headers ({{ headerCount }} {{ headerCount === 1 ? "entry" : "entries" }})
                </div>
                <ShowHideButton v-model="showHeaders" size="xs" color="sky-invert" />
            </div>
            <div v-if="showHeaders" class="mt-3">
                <div v-for="header in requestHeaders" :key="header.name" class="flex-x space-x-2 py-1">
                    <div :class="themeClass('text-slate-400', 'text-slate-500')" class="min-w-32">{{ header.name }}:</div>
                    <div>{{ header.value }}</div>
                </div>
            </div>
        </div>

        <!-- Request Section -->
        <div :class="themeClass('border-slate-600', 'border-slate-200')" class="border-t py-2">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div :class="themeClass('text-slate-300', 'text-slate-600')" class="font-semibold">
                        Request ({{ fNumber(requestBytes) }} bytes)
                    </div>
                    <ShowHideButton v-model="showRequest" size="xs" color="sky-invert" />
                </div>
                <div v-if="isOpenAiResponsesApi" class="flex-x gap-1">
                    <ActionButton
                        label="Formatted"
                        size="xxs"
                        :color="showRawRequest ? (isDark ? 'slate' : 'slate-soft') : 'sky'"
                        @click="showRawRequest = false"
                    />
                    <ActionButton
                        label="Raw"
                        size="xxs"
                        :color="showRawRequest ? 'sky' : (isDark ? 'slate' : 'slate-soft')"
                        @click="showRawRequest = true"
                    />
                </div>
            </div>
            <div v-if="showRequest" class="mt-3">
                <OpenAiApiRequestCard
                    v-if="isOpenAiResponsesApi && requestData && !showRawRequest"
                    :request-data="requestData"
                />
                <div v-else class="overflow-x-auto max-w-full">
                    <CodeViewer :model-value="apiLog.request" format="json" :theme="isDark ? 'dark' : 'light'" />
                </div>
            </div>
        </div>

        <!-- Response Section -->
        <div :class="themeClass('border-slate-600', 'border-slate-200')" class="border-t py-2">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div :class="themeClass('text-slate-300', 'text-slate-600')" class="font-semibold">
                        Response ({{ fNumber(responseBytes) }} bytes)
                    </div>
                    <ShowHideButton v-model="showResponse" size="xs" color="sky-invert" />
                </div>
                <div v-if="isOpenAiResponsesApi" class="flex-x gap-1">
                    <ActionButton
                        label="Formatted"
                        size="xxs"
                        :color="showRawResponse ? (isDark ? 'slate' : 'slate-soft') : 'sky'"
                        @click="showRawResponse = false"
                    />
                    <ActionButton
                        label="Raw"
                        size="xxs"
                        :color="showRawResponse ? 'sky' : (isDark ? 'slate' : 'slate-soft')"
                        @click="showRawResponse = true"
                    />
                </div>
            </div>
            <div v-if="showResponse" class="mt-3">
                <OpenAiApiResponseCard
                    v-if="isOpenAiResponsesApi && responseData && !showRawResponse"
                    :response-data="responseData"
                />
                <div v-else class="overflow-x-auto max-w-full">
                    <CodeViewer :model-value="apiLog.response" format="json" :theme="isDark ? 'dark' : 'light'" />
                </div>
            </div>
        </div>
    </QCard>
</template>
<script setup lang="ts">
import { useApiLogUpdates } from "@/components/Modules/Audits/ApiLogs/useApiLogUpdates";
import OpenAiApiRequestCard from "@/components/Modules/Audits/ApiLogs/OpenAiApiRequestCard.vue";
import OpenAiApiResponseCard from "@/components/Modules/Audits/ApiLogs/OpenAiApiResponseCard.vue";
import { ApiLog } from "@/components/Modules/Audits/audit-requests";
import { useAuditCardTheme } from "@/composables/useAuditCardTheme";
import ApiStatusCodeBadge from "@/components/Shared/ApiStatusCodeBadge.vue";
import ElapsedTimer from "@/components/Shared/ElapsedTimer.vue";
import {
    ActionButton,
    CodeViewer,
    fDateTime,
    fDateTimeMs,
    fNumber,
    LabelPillWidget,
    ShowHideButton
} from "quasar-ui-danx";
import { computed, ref } from "vue";

const props = defineProps<{
    apiLog: ApiLog
}>();

const { isDark, themeClass } = useAuditCardTheme();

// Subscribe to real-time updates for API logs in progress
useApiLogUpdates(() => props.apiLog);

const requestHeaders = computed(() => Object.keys(props.apiLog.request_headers).map(name => ({
    name,
    value: typeof props.apiLog.request_headers[name] === "string" ? props.apiLog.request_headers[name] : props.apiLog.request_headers[name].join(", ")
})));

const methodClass = computed(() => {
    switch (props.apiLog.method) {
        case "GET":
            return "bg-sky-600";
        case "POST":
            return "bg-lime-700";
        case "PUT":
            return "bg-amber-600";
        case "PATCH":
            return "bg-indigo-500";
        case "DELETE":
        default:
            return "bg-red-800";
    }
});

const decodedUrl = computed(() => decodeURIComponent(props.apiLog.url).replace(/ /g, "+"));

// Visibility toggles
const showHeaders = ref(false);
const showRequest = ref(false);
const showResponse = ref(false);

// Raw/formatted toggles for OpenAI
const showRawRequest = ref(false);
const showRawResponse = ref(false);

// Computed sizes
const headerCount = computed(() => Object.keys(props.apiLog.request_headers || {}).length);

const requestBytes = computed(() => {
    const data = typeof props.apiLog.request === "string" ? props.apiLog.request : JSON.stringify(props.apiLog.request);
    return data?.length || 0;
});

const responseBytes = computed(() => {
    const data = typeof props.apiLog.response === "string" ? props.apiLog.response : JSON.stringify(props.apiLog.response);
    return data?.length || 0;
});

// Detect OpenAI Responses API
const isOpenAiResponsesApi = computed(() => {
    return props.apiLog?.url?.includes("api.openai.com/v1/responses");
});

// JSON parsing logic for request/response
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

const requestData = computed(() => parseJsonSafely(props.apiLog.request));
const responseData = computed(() => parseJsonSafely(props.apiLog.response));
</script>
