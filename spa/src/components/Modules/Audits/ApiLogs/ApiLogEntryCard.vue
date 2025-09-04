<template>
    <QCard class="bg-slate-700 p-3">
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
                <ShowHideButton v-model="showRequest" label="Request" class="bg-slate-800" />
                <ShowHideButton v-model="showResponse" label="Response" class="bg-slate-800" />
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
        <div class="mt-5">
            <div v-for="header in requestHeaders" :key="header.name" class="flex-x space-x-2 py-1">
                <div class="text-slate-400 min-w-32">{{ header.name }}:</div>
                <div>{{ header.value }}</div>
            </div>
        </div>
        <div v-if="showRequest" class="my-4">
            <div class="font-bold mb-2">Request</div>
            <MarkdownEditor :model-value="apiLog.request" format="json" readonly />
        </div>
        <div v-if="showResponse" class="my-4">
            <div class="font-bold mb-2">Response</div>
            <MarkdownEditor :model-value="apiLog.response" format="json" readonly />
        </div>
    </QCard>
</template>
<script setup lang="ts">
import MarkdownEditor from "@/components/MarkdownEditor/MarkdownEditor";
import { ApiLog } from "@/components/Modules/Audits/audit-requests";
import { fDateTime, fDateTimeMs, fMillisecondsToDuration, LabelPillWidget, ShowHideButton } from "quasar-ui-danx";
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

const showRequest = ref(false);
const showResponse = ref(false);
</script>
