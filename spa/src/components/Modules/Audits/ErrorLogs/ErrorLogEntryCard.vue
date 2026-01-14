<template>
	<QCard :class="themeClass('bg-slate-700', 'bg-white shadow border border-slate-200')" class="p-3">
		<div class="flex items-center">
			<div class="flex items-center space-x-3 flex-grow">
				<div class="bg-red-900 rounded-2xl px-3 py-1">{{ levelName }}</div>
				<div :class="themeClass('', 'text-slate-800')" class="font-semibold">{{ error.error_class }}</div>
				<div :class="themeClass('bg-slate-500', 'bg-slate-200 text-slate-700')" class="px-1 rounded-lg">{{ error.code }}</div>
			</div>
			<div class="flex items-center">
				<ShowHideButton v-model="showVendor" label="Vendor Files" :class="themeClass('bg-slate-800', 'bg-slate-200 text-slate-700')" class="mr-2" />
				<div :class="themeClass('bg-slate-900', 'bg-slate-100 text-slate-700')" class="px-4 py-1 rounded-2xl">
					{{ fDateTime(error.created_at) }}
				</div>
			</div>
		</div>
		<div :class="themeClass('', 'text-slate-700')" class="whitespace-pre-wrap my-4">
			{{ error.message }}
		</div>
		<div :class="themeClass('bg-slate-900', 'bg-slate-100')" class="flex items-center space-x-2 text-base px-3 py-1">
			<div :class="themeClass('text-slate-400', 'text-slate-500')">{{ error.file }}</div>
			<div :class="themeClass('', 'text-slate-700')">@ {{ error.line }}</div>
		</div>
		<div>
			<div
				v-for="(trace, index) in stackTrace"
				:key="index"
				:class="themeClass('bg-slate-800', 'bg-slate-50 border border-slate-200')"
				class="flex-x space-x-2 text-sm my-1 px-3 py-1 rounded"
			>
				<div class="flex-grow flex-nowrap flex items-center space-x-1">
					<div class="text-sky-600">
						{{ trace.file?.replace(/^.*\//, "") }}
						<QTooltip>{{ trace.file }}</QTooltip>
					</div>
					<div class="text-sky-500 text-no-wrap">@ {{ trace.line }}</div>
				</div>
				<div :class="themeClass('text-slate-400', 'text-slate-500')" class="flex items-center space-x-2">
					<div>
						{{ trace.type?.replace(/^.*\//, "") }}
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
import { useAuditCardTheme } from "@/composables/useAuditCardTheme";
import { fDateTime, ShowHideButton } from "quasar-ui-danx";
import { computed, ref } from "vue";

const { themeClass } = useAuditCardTheme();

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
