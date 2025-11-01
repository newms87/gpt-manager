<template>
	<QChip
		clickable
		dense
		class="cursor-pointer hover:opacity-80 px-3 py-1.5 text-xs font-medium border-l-4 transition-all"
		:class="[colorConfig.bg, colorConfig.text, colorConfig.border]"
	>
		<!-- Status icon -->
		<component
			:is="statusIcon"
			class="w-3 h-3 mr-1.5 flex-shrink-0"
		/>

		<QPopupProxy
			anchor="bottom left"
			self="top left"
			class="bg-slate-800 rounded-lg shadow-lg border border-slate-700"
		>
			<div class="p-4 max-w-md">
				<div class="flex items-center gap-2 mb-3">
					<component
						:is="statusIcon"
						class="w-4 h-4"
						:class="colorConfig.text"
					/>
					<h4 class="text-sm font-semibold text-slate-200">
						Job {{ jobEntry.status }}
					</h4>
				</div>

				<div class="space-y-3">
					<!-- Full Job Name -->
					<div class="border-b border-slate-700 pb-2">
						<div class="text-xs font-medium text-slate-400">
							Job Class
						</div>
						<div class="text-sm text-slate-200 font-mono break-all whitespace-pre-wrap">
							{{ jobEntry.fullJobName }}
						</div>
					</div>

					<!-- Job ID -->
					<div class="border-b border-slate-700 pb-2">
						<div class="text-xs font-medium text-slate-400">
							Job ID
						</div>
						<div class="text-sm text-slate-200 font-mono whitespace-pre-wrap">
							{{ jobEntry.jobId }}
						</div>
					</div>

					<!-- Job Identifier -->
					<div
						v-if="jobEntry.identifier"
						class="border-b border-slate-700 pb-2"
					>
						<div class="text-xs font-medium text-slate-400">
							Identifier
						</div>
						<div class="text-sm text-slate-200 font-mono break-all whitespace-pre-wrap">
							{{ jobEntry.identifier }}
						</div>
					</div>

					<!-- Timing Info -->
					<div
						v-if="jobEntry.timing"
						class="pb-2"
					>
						<div class="text-xs font-medium text-slate-400">
							Timing
						</div>
						<div class="text-sm text-slate-200 font-mono whitespace-pre-wrap">
							{{ jobEntry.timing }}
						</div>
					</div>

					<!-- Status Info -->
					<div class="flex items-center gap-2 pt-2">
						<LabelPillWidget
							:label="jobEntry.status"
							:color="statusColor"
							size="xs"
						/>
					</div>
				</div>
			</div>
		</QPopupProxy>

		<span class="font-mono">{{ jobEntry.jobName }} ({{ jobEntry.jobId }})</span>
	</QChip>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { QChip, QPopupProxy } from 'quasar';
import { LabelPillWidget } from 'quasar-ui-danx';
import {
	FaSolidPlay as HandlingIcon,
	FaSolidCheck as CompletedIcon,
	FaSolidX as FailedIcon
} from 'danx-icon';
import type { JobLogEntry } from './useLogParser';

const props = defineProps<{
	jobEntry: JobLogEntry;
}>();

const statusIcon = computed(() => {
	switch (props.jobEntry.status) {
		case 'Handling':
			return HandlingIcon;
		case 'Completed':
			return CompletedIcon;
		case 'Failed':
			return FailedIcon;
		default:
			return HandlingIcon;
	}
});

const statusColor = computed(() => {
	switch (props.jobEntry.status) {
		case 'Handling':
			return 'sky';
		case 'Completed':
			return 'green';
		case 'Failed':
			return 'red';
		default:
			return 'slate';
	}
});

interface ColorConfig {
	bg: string;
	text: string;
	border: string;
}

const colorConfig = computed<ColorConfig>(() => {
	switch (props.jobEntry.status) {
		case 'Handling':
			return {
				bg: 'bg-sky-950',
				text: 'text-sky-400',
				border: 'border-sky-500'
			};
		case 'Completed':
			return {
				bg: 'bg-green-950',
				text: 'text-green-400',
				border: 'border-green-500'
			};
		case 'Failed':
			return {
				bg: 'bg-red-950',
				text: 'text-red-400',
				border: 'border-red-500'
			};
		default:
			return {
				bg: 'bg-slate-950',
				text: 'text-slate-400',
				border: 'border-slate-500'
			};
	}
});
</script>
