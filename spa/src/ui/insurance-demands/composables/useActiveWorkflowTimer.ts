import type { ComputedRef, Ref } from "vue";
import { onMounted, onUnmounted, ref, unref, watch } from "vue";

/**
 * Composable for managing a reactive timer that updates when workflows are active.
 * This is used to provide live runtime updates for active workflows.
 *
 * @param hasActiveWorkflows - A ref or computed that indicates if there are active workflows
 * @returns An object containing the reactive currentTime ref
 */
export function useActiveWorkflowTimer(hasActiveWorkflows: Ref<boolean> | ComputedRef<boolean>) {
	// Reactive timer for live updates of running workflows
	const currentTime = ref(Date.now());
	let intervalId: NodeJS.Timeout | null = null;

	// Setup timer for live updates only when needed
	const setupTimer = () => {
		if (!intervalId && unref(hasActiveWorkflows)) {
			intervalId = setInterval(() => {
				currentTime.value = Date.now();
			}, 1000); // Update every second
		}
	};

	const clearTimer = () => {
		if (intervalId) {
			clearInterval(intervalId);
			intervalId = null;
		}
	};

	// Watch for changes in active workflows to manage timer
	watch(hasActiveWorkflows, (hasActive) => {
		if (hasActive) {
			setupTimer();
		} else {
			clearTimer();
		}
	}, { immediate: true });

	onMounted(() => {
		setupTimer();
	});

	onUnmounted(() => {
		clearTimer();
	});

	return {
		currentTime
	};
}
