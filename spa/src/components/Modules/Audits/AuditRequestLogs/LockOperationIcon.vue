<template>
	<QTooltip
		anchor="top middle"
		self="bottom middle"
	>
		{{ tooltipText }}
	</QTooltip>
	<component
		:is="iconComponent"
		class="w-4 h-4 inline-block"
		:class="iconColorClass"
	/>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { QTooltip } from 'quasar';
import {
	FaSolidLock as AcquiredIcon,
	FaSolidLockOpen as ReleasedIcon,
	FaSolidClock as WaitIcon
} from 'danx-icon';
import type { LockOperation } from './useLogParser';

const props = defineProps<{
	operation: LockOperation;
}>();

const iconComponent = computed(() => {
	switch (props.operation.type) {
		case 'ACQUIRED':
			return AcquiredIcon;
		case 'RELEASED':
			return ReleasedIcon;
		case 'WAIT':
			return WaitIcon;
	}
});

const iconColorClass = computed(() => {
	switch (props.operation.type) {
		case 'ACQUIRED':
			return 'text-green-400';
		case 'RELEASED':
			return 'text-blue-400';
		case 'WAIT':
			return 'text-amber-400';
	}
});

const tooltipText = computed(() => {
	switch (props.operation.type) {
		case 'ACQUIRED':
			return 'Lock Acquired';
		case 'RELEASED':
			return 'Lock Released';
		case 'WAIT':
			return 'Waiting for Lock';
	}
});
</script>
