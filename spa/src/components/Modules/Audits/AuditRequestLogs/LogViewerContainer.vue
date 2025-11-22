<template>
	<div class="log-viewer-container flex flex-col h-full bg-gray-950">
		<!-- Combined Filter Bar and Controls -->
		<div class="flex items-center justify-between gap-3 px-3 py-2 bg-slate-800 border-b border-slate-700">
			<!-- Left side: Search + Level Filter -->
			<div class="flex items-center gap-3 flex-grow">
				<LogFilterBar
					:keyword="keyword"
					:selected-levels="selectedLevels"
					:available-levels="logLevels"
					@update:keyword="keyword = $event"
					@update:selected-levels="selectedLevels = $event"
				/>
			</div>

			<!-- Right side: Controls -->
			<LogViewerControls
				:show-timestamp="showTimestamp"
				:auto-scroll="autoScroll"
				:show-locks="showLocks"
				@update:show-timestamp="showTimestamp = $event"
				@update:auto-scroll="autoScroll = $event"
				@update:show-locks="showLocks = $event"
				@export="handleExport"
			/>
		</div>

		<!-- Virtual Scroll Container -->
		<div ref="scrollContainer" class="flex-grow overflow-hidden">
			<QVirtualScroll
				ref="virtualScrollRef"
				:items="filteredLines"
				virtual-scroll-item-size="48"
				class="h-full"
			>
				<template #default="{ item }">
					<LogLineComponent
						:log-line="item"
						:show-timestamp="showTimestamp"
						@filter="onFilterByEntity"
					/>
				</template>
			</QVirtualScroll>
		</div>

		<!-- Empty State -->
		<div
			v-if="filteredLines.length === 0"
			class="absolute inset-0 flex items-center justify-center text-slate-500"
		>
			<div class="text-center">
				<div class="text-lg mb-2">No logs to display</div>
				<div v-if="hasActiveFilters" class="text-sm">
					Try adjusting your filters
				</div>
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import { computed, ref, watch, nextTick } from 'vue';
import { QVirtualScroll } from 'quasar';
import LogFilterBar from './LogFilterBar.vue';
import LogViewerControls from './LogViewerControls.vue';
import LogLineComponent from './LogLineComponent.vue';
import { useLogParser } from './useLogParser';
import { filterLogLines, exportLogs } from './logHelpers';
import type { LogLevel } from './logHelpers';

const props = defineProps<{
	logs: string | null | undefined;
}>();

// Reactive state
const keyword = ref('');
const selectedLevels = ref<LogLevel[]>([]);
const showTimestamp = ref(localStorage.getItem('logViewer.showTimestamp') === 'true');
const autoScroll = ref(localStorage.getItem('logViewer.autoScroll') !== 'false'); // default true
const showLocks = ref(localStorage.getItem('logViewer.showLocks') === 'true');
const virtualScrollRef = ref<InstanceType<typeof QVirtualScroll> | null>(null);
const scrollContainer = ref<HTMLElement | null>(null);

// Parse logs
const logsRef = computed(() => props.logs);
const { parsedLines, logLevels } = useLogParser(logsRef);

// Filter logs
const filteredLines = computed(() => {
	const rawLines = parsedLines.value.map(line => line.raw);
	const filtered = filterLogLines(rawLines, keyword.value, selectedLevels.value, showLocks.value);
	return filtered.map(raw => parsedLines.value.find(line => line.raw === raw)!).filter(Boolean);
});

const hasActiveFilters = computed(() => {
	return keyword.value !== '' || selectedLevels.value.length > 0;
});

// Save preferences to localStorage
watch(showTimestamp, (newValue) => {
	localStorage.setItem('logViewer.showTimestamp', String(newValue));
});

watch(autoScroll, (newValue) => {
	localStorage.setItem('logViewer.autoScroll', String(newValue));
});

watch(showLocks, (newValue) => {
	localStorage.setItem('logViewer.showLocks', String(newValue));
});

// Auto-scroll to bottom when new logs arrive
watch(filteredLines, async () => {
	if (autoScroll.value) {
		await nextTick();
		scrollToBottom();
	}
}, { flush: 'post' });

const scrollToBottom = () => {
	if (virtualScrollRef.value) {
		virtualScrollRef.value.scrollTo(filteredLines.value.length - 1);
	}
};

const handleExport = () => {
	if (props.logs) {
		// Apply the same filters used for display
		const rawLines = parsedLines.value.map(line => line.raw);
		const filteredRawLines = filterLogLines(rawLines, keyword.value, selectedLevels.value, showLocks.value);
		const filteredLogsString = filteredRawLines.join('\n');

		const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
		exportLogs(filteredLogsString, `logs-${timestamp}.txt`);
	}
};

const onFilterByEntity = (content: string) => {
	keyword.value = content;
};
</script>

<style lang="scss" scoped>
.log-viewer-container {
	position: relative;
}
</style>
