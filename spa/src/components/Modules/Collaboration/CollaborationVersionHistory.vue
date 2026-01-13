<template>
	<div class="bg-white rounded-lg border border-slate-200">
		<!-- Header -->
		<div class="flex items-center p-3 border-b border-slate-200">
			<HistoryIcon class="w-4 h-4 text-slate-500 mr-2" />
			<span class="text-sm font-semibold text-slate-700">Version History</span>
			<span class="text-xs text-slate-500 ml-2">({{ history.length }} versions)</span>
		</div>

		<!-- Version list -->
		<div class="max-h-64 overflow-y-auto">
			<div
				v-for="entry in history"
				:key="entry.id"
				class="flex items-center p-3 border-b border-slate-100 hover:bg-slate-50 transition-colors"
				:class="{ 'bg-sky-50': isCurrentVersion(entry) }"
			>
				<div class="flex-grow">
					<div class="flex items-center">
						<span class="text-sm text-slate-700">
							{{ fDateTime(entry.created_at) }}
						</span>
						<span
							v-if="isCurrentVersion(entry)"
							class="ml-2 text-xs bg-sky-600 text-white px-2 py-0.5 rounded"
						>
							Current
						</span>
					</div>
					<div v-if="entry.user_name" class="text-xs text-slate-500 mt-1">
						<UserIcon class="w-3 h-3 inline mr-1" />
						{{ entry.user_name }}
					</div>
				</div>

				<div class="flex items-center gap-2">
					<ActionButton
						type="view"
						size="xs"
						color="slate"
						tooltip="Preview this version"
						@click="$emit('preview', entry.id)"
					/>
					<ActionButton
						v-if="!isCurrentVersion(entry)"
						type="refresh"
						size="xs"
						color="sky"
						tooltip="Restore this version"
						@click="confirmRestore(entry)"
					/>
				</div>
			</div>

			<div v-if="history.length === 0" class="p-4 text-center text-slate-500">
				No version history available
			</div>
		</div>

		<!-- Restore confirmation dialog -->
		<ConfirmDialog
			v-if="restoreEntry"
			title="Restore Version?"
			:content="restoreConfirmMessage"
			confirm-label="Restore"
			confirm-class="bg-sky-600 text-white"
			@confirm="onRestoreConfirm"
			@close="restoreEntry = null"
		/>
	</div>
</template>

<script setup lang="ts">
import { TemplateDefinitionHistory } from "@/components/Modules/Collaboration/types";
import {
	FaRegularUser as UserIcon,
	FaSolidClockRotateLeft as HistoryIcon
} from "danx-icon";
import { ActionButton, ConfirmDialog, fDateTime } from "quasar-ui-danx";
import { computed, ref } from "vue";

const props = withDefaults(defineProps<{
	history: TemplateDefinitionHistory[];
	currentVersion?: string;
}>(), {
	currentVersion: undefined
});

const emit = defineEmits<{
	restore: [historyId: number];
	preview: [historyId: number];
}>();

const restoreEntry = ref<TemplateDefinitionHistory | null>(null);

const restoreConfirmMessage = computed(() => {
	if (!restoreEntry.value) return "";
	return `This will restore the content to the version from ${fDateTime(restoreEntry.value.created_at)}. Your current content will be added to the version history.`;
});

/**
 * Check if entry is the current version
 */
function isCurrentVersion(entry: TemplateDefinitionHistory): boolean {
	if (!props.currentVersion) return false;
	return entry.created_at === props.currentVersion;
}

/**
 * Show restore confirmation
 */
function confirmRestore(entry: TemplateDefinitionHistory) {
	restoreEntry.value = entry;
}

/**
 * Handle restore confirmation
 */
function onRestoreConfirm() {
	if (restoreEntry.value) {
		emit("restore", restoreEntry.value.id);
		restoreEntry.value = null;
	}
}
</script>
