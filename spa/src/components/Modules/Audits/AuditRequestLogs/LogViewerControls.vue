<template>
	<div class="flex items-center gap-2">
		<!-- Toggle Timestamp Format -->
		<ActionButton
			:icon="ClockIcon"
			:color="showTimestamp ? 'blue' : 'slate'"
			tooltip="Toggle full timestamp"
			size="sm"
			@click="$emit('update:showTimestamp', !showTimestamp)"
		/>

		<!-- Auto-scroll Toggle -->
		<ActionButton
			:icon="ArrowDownIcon"
			:color="autoScroll ? 'green' : 'slate'"
			tooltip="Auto-scroll to newest logs"
			size="sm"
			@click="$emit('update:autoScroll', !autoScroll)"
		/>

		<!-- Show Locks Toggle -->
		<ActionButton
			:icon="showLocks ? LockIcon : LockOpenIcon"
			:color="showLocks ? 'orange' : 'slate'"
			tooltip="Show/hide lock entries"
			size="sm"
			@click="$emit('update:showLocks', !showLocks)"
		/>

		<!-- Export Button -->
		<ActionButton
			type="export"
			color="purple"
			size="sm"
			tooltip="Export logs to file"
			@click="$emit('export')"
		/>
	</div>
</template>

<script setup lang="ts">
import { ActionButton } from 'quasar-ui-danx';
import {
	FaSolidClock as ClockIcon,
	FaSolidArrowDown as ArrowDownIcon,
	FaSolidLock as LockIcon,
	FaSolidLockOpen as LockOpenIcon
} from 'danx-icon';

defineProps<{
	showTimestamp: boolean;
	autoScroll: boolean;
	showLocks: boolean;
}>();

defineEmits<{
	'update:showTimestamp': [value: boolean];
	'update:autoScroll': [value: boolean];
	'update:showLocks': [value: boolean];
	'export': [];
}>();
</script>
