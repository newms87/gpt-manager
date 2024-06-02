<template>
	<QCard class="bg-slate-700 p-3">
		<div class="flex items-center flex-nowrap overflow-hidden">
			<div class="flex items-center flex-nowrap space-x-3 flex-grow">
				<div class="rounded-2xl px-3 py-1" :class="methodClass">{{ apiLog.method }}</div>
				<div class="bg-slate-500 rounded-2xl px-3 py-1">{{ apiLog.status_code }}</div>
				<div class="font-semibold text-base text-no-wrap overflow-hidden overflow-ellipsis max-w-[50rem]">
					{{ decodedUrl }}
					<QTooltip>{{ decodedUrl }}</QTooltip>
				</div>
			</div>
			<div class="flex items-center">
				<div class="bg-slate-900 px-4 py-1 rounded-2xl text-no-wrap">
					{{ fDateTime(apiLog.created_at) }}
				</div>
			</div>
		</div>
		<div class="mt-5">
			<div v-for="header in requestHeaders" :key="header.name" class="flex items-center flex-nowrap space-x-2 py-1">
				<div class="text-slate-400 min-w-32">{{ header.name }}:</div>
				<div>{{ header.value }}</div>
			</div>
		</div>
		<div class="my-4">
			<div class="font-bold mb-2">Request</div>
			<MarkdownEditor :model-value="fMarkdownJSON(apiLog.request)" readonly />
		</div>
		<div class="my-4">
			<div class="font-bold mb-2">Response</div>
			<MarkdownEditor :model-value="fMarkdownJSON(apiLog.response)" readonly />
		</div>
	</QCard>
</template>
<script setup lang="ts">
import MarkdownEditor from "@/components/MardownEditor/MarkdownEditor";
import { ApiLog } from "@/components/Modules/Audits/audit-requests";
import { fDateTime, fMarkdownJSON } from "quasar-ui-danx";
import { computed } from "vue";

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
</script>
