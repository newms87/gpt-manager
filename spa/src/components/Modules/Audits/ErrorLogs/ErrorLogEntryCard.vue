<template>
	<QCard class="bg-slate-700 p-3">
		<div class="flex items-center">
			<div class="flex items-center space-x-3 flex-grow">
				<div class="bg-red-900 rounded-2xl px-3 py-1">{{ levelName }}</div>
				<div class="font-semibold">{{ error.error_class }}</div>
				<div class="px-1 bg-slate-500 rounded-lg">{{ error.code }}</div>
			</div>
			<div class="flex items-center">
				<ShowHideButton v-model="showVendor" label="Vendor Files" class="bg-slate-800 mr-2" />
				<div class="bg-slate-900 px-4 py-1 rounded-2xl">
					{{ fDateTime(error.created_at) }}
				</div>
			</div>
		</div>
		<div class="whitespace-pre-wrap my-4">
			{{ error.message }}
		</div>
		<div class="flex items-center space-x-2 text-base bg-slate-900 px-3 py-1">
			<div class="text-slate-400">{{ error.file }}</div>
			<div>@ {{ error.line }}</div>
		</div>
		<div>
			<div
				v-for="(trace, index) in stackTrace"
				:key="index"
				class="flex-x space-x-2 text-sm bg-slate-800 my-1 px-3 py-1 rounded"
			>
				<div class="flex-grow flex-nowrap flex items-center space-x-1">
					<div class="text-sky-600">
						{{ trace.file.replace(/^.*\//, "") }}
						<QTooltip>{{ trace.file }}</QTooltip>
					</div>
					<div class="text-sky-500 text-no-wrap">@ {{ trace.line }}</div>
				</div>
				<div class="flex items-center space-x-2 text-slate-400">
					<div>
						{{ trace.type.replace(/^.*\//, "") }}
						<QTooltip>{{ trace.type }}</QTooltip>
					</div>
					<div>{{ trace.class }}</div>
					<div class="text-bold">{{ trace.function }}</div>
				</div>
			</div>
		</div>
	</QCard>
</template>
<script setup lang="ts">
import { ErrorLogEntry } from "@/components/Modules/Audits/audit-requests";
import { fDateTime, ShowHideButton } from "quasar-ui-danx";
import { computed, ref } from "vue";

const props = defineProps<{
	error: ErrorLogEntry
}>();

const showVendor = ref(false);
const levelNames = {
	100: "INFO",
	200: "DEBUG",
	250: "NOTICE",
	300: "WARNING",
	400: "ERROR",
	500: "CRITICAL",
	550: "ALERT",
	600: "EMERGENCY"
};

const levelName = computed(() => levelNames[props.error.level] || "UNKNOWN");

const stackTrace = computed(() => showVendor.value ? props.error.stack_trace : props.error.stack_trace.filter(trace => !trace.file?.includes("/vendor/")));
</script>
