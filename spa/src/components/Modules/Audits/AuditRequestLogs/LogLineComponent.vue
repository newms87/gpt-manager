<template>
	<div
		class="log-line flex items-start justify-between gap-3 px-3 py-2 hover:brightness-110 transition-all"
		:class="[
			logLine.jobEntry ? 'job-entry-line border-l-4' : '',
			logLine.jobEntry ? getJobEntryBorderClass(logLine.jobEntry.status) : '',
			logLine.jobEntry ? getJobEntryBackgroundClass(logLine.jobEntry.status) : (logLine.level ? getLogLevelConfig(logLine.level).bgClass : 'bg-slate-950')
		]"
	>
		<!-- Message with embedded objects -->
		<div class="flex-grow min-w-0 text-sm text-slate-300 break-words">
			<LogMessageRenderer :log-line="logLine" @filter="$emit('filter', $event)" />
		</div>

		<!-- Right side: Timestamp -->
		<div class="flex items-center flex-shrink-0">
			<!-- Timestamp pill -->
			<LabelPillWidget
				v-if="logLine.dateTime"
				:label="showTimestamp ? fDateTime(logLine.dateTime, { format: 'M/d/yy h:mm:ssa' }) : logLine.time"
				color="slate-mid"
				size="xs"
			/>
		</div>
	</div>
</template>

<script setup lang="ts">
import { fDateTime, LabelPillWidget } from 'quasar-ui-danx';
import LogMessageRenderer from './LogMessageRenderer.vue';
import { getLogLevelConfig } from './logHelpers';
import type { ParsedLogLine, JobLogEntry } from './useLogParser';

defineProps<{
	logLine: ParsedLogLine;
	showTimestamp: boolean;
}>();

defineEmits<{
	filter: [content: string];
}>();

/**
 * Get border color class for job entry based on status
 */
function getJobEntryBorderClass(status: JobLogEntry['status']): string {
	switch (status) {
		case 'Handling':
			return 'border-sky-500';
		case 'Completed':
			return 'border-green-500';
		case 'Failed':
			return 'border-red-500';
		default:
			return 'border-slate-500';
	}
}

/**
 * Get background color class for job entry based on status
 */
function getJobEntryBackgroundClass(status: JobLogEntry['status']): string {
	switch (status) {
		case 'Handling':
			return 'bg-sky-900/30';
		case 'Completed':
			return 'bg-green-900/30';
		case 'Failed':
			return 'bg-red-900/30';
		default:
			return 'bg-slate-900';
	}
}
</script>

<style lang="scss" scoped>
.log-line {
	font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;

	&.job-entry-line {
		// Extra padding and shadow to make job entries stand out
		padding-left: 1rem;
		box-shadow: inset 4px 0 0 0;
		font-weight: 500;

		&:hover {
			transform: translateX(2px);
			box-shadow: inset 4px 0 0 0, 2px 0 8px rgba(0, 0, 0, 0.3);
		}
	}
}
</style>
