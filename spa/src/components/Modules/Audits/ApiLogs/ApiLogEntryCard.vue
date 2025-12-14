<template>
    <QCard class="bg-slate-700 p-3 overflow-hidden min-w-0">
        <div class="flex-x overflow-hidden">
            <div class="flex-x space-x-3 flex-grow">
                <LabelPillWidget :label="'api-log: ' + apiLog.id" color="sky" size="xs" />
                <div class="rounded-2xl px-3 py-1" :class="methodClass">{{ apiLog.method }}</div>
                <div
                    class="rounded-2xl px-3 py-1"
                    :class="{
					'bg-red-800': apiLog.status_code >= 400,
					'bg-yellow-700': apiLog.status_code < 400 && apiLog.status_code >= 300,
					'bg-green-800': apiLog.status_code < 300
				}"
                >
                    {{ apiLog.status_code }}
                </div>
            </div>
            <div class="flex-x flex-shrink-0 space-x-2">
                <LabelPillWidget :label="fMillisecondsToDuration(apiLog.run_time_ms)" color="blue" size="xs" />
                <LabelPillWidget color="slate" size="sm">
                    {{ fDateTime(apiLog.created_at) }}
                    <QTooltip class="text-base">{{ fDateTimeMs(apiLog.created_at) }}</QTooltip>
                </LabelPillWidget>
            </div>
        </div>
        <div class="mt-5 font-semibold text-base text-no-wrap overflow-hidden overflow-ellipsis max-w-full">
            {{ decodedUrl }}
            <QTooltip>{{ decodedUrl }}</QTooltip>
        </div>

        <!-- Headers Section -->
        <div class="mt-4 border-t border-slate-600 py-2">
            <div class="flex items-center gap-2">
                <div class="font-semibold text-slate-300">
                    Headers ({{ headerCount }} {{ headerCount === 1 ? "entry" : "entries" }})
                </div>
                <ShowHideButton v-model="showHeaders" size="xs" color="sky-invert" />
            </div>
            <div v-if="showHeaders" class="mt-3">
                <div v-for="header in requestHeaders" :key="header.name" class="flex-x space-x-2 py-1">
                    <div class="text-slate-400 min-w-32">{{ header.name }}:</div>
                    <div>{{ header.value }}</div>
                </div>
            </div>
        </div>

        <!-- Request Section -->
        <div class="border-t border-slate-600 py-2">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="font-semibold text-slate-300">
                        Request ({{ fNumber(requestBytes) }} bytes)
                    </div>
                    <ShowHideButton v-model="showRequest" size="xs" color="sky-invert" />
                </div>
                <div v-if="isOpenAiResponsesApi" class="flex-x gap-1">
                    <QBtn
                        flat
                        dense
                        size="xs"
                        :class="showRawRequest ? 'text-slate-400' : 'text-sky-400 bg-slate-600'"
                        label="Formatted"
                        @click="showRawRequest = false"
                    />
                    <QBtn
                        flat
                        dense
                        size="xs"
                        :class="showRawRequest ? 'text-sky-400 bg-slate-600' : 'text-slate-400'"
                        label="Raw"
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
                    <MarkdownEditor :model-value="apiLog.request" format="json" readonly />
                </div>
            </div>
        </div>

        <!-- Response Section -->
        <div class="border-t border-slate-600 py-2">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="font-semibold text-slate-300">
                        Response ({{ fNumber(responseBytes) }} bytes)
                    </div>
                    <ShowHideButton v-model="showResponse" size="xs" color="sky-invert" />
                </div>
                <div v-if="isOpenAiResponsesApi" class="flex-x gap-1">
                    <QBtn
                        flat
                        dense
                        size="xs"
                        :class="showRawResponse ? 'text-slate-400' : 'text-sky-400 bg-slate-600'"
                        label="Formatted"
                        @click="showRawResponse = false"
                    />
                    <QBtn
                        flat
                        dense
                        size="xs"
                        :class="showRawResponse ? 'text-sky-400 bg-slate-600' : 'text-slate-400'"
                        label="Raw"
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
                    <MarkdownEditor :model-value="apiLog.response" format="json" readonly />
                </div>
            </div>
        </div>
    </QCard>
</template>
<script setup lang="ts">
import MarkdownEditor from "@/components/MarkdownEditor/MarkdownEditor";
import OpenAiApiRequestCard from "@/components/Modules/Audits/ApiLogs/OpenAiApiRequestCard.vue";
import OpenAiApiResponseCard from "@/components/Modules/Audits/ApiLogs/OpenAiApiResponseCard.vue";
import { ApiLog } from "@/components/Modules/Audits/audit-requests";
import {
    fDateTime,
    fDateTimeMs,
    fMillisecondsToDuration,
    fNumber,
    LabelPillWidget,
    ShowHideButton
} from "quasar-ui-danx";
import { computed, ref } from "vue";

const props = defineProps<{
    apiLog: ApiLog
}>();

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
