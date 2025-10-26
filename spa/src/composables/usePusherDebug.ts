import { onMounted, onUnmounted, ref } from "vue";

const showDebugPanel = ref(false);

export function toggleDebugPanel() {
	showDebugPanel.value = !showDebugPanel.value;
}

export function openDebugPanel() {
	showDebugPanel.value = true;
}

export function closeDebugPanel() {
	showDebugPanel.value = false;
}

export function usePusherDebug() {
	// Keyboard shortcut handler
	function handleKeyPress(event: KeyboardEvent) {
		// Ctrl+Shift+D (Windows/Linux) or Cmd+Shift+D (Mac)
		if ((event.ctrlKey || event.metaKey) && event.shiftKey && event.key === "D") {
			event.preventDefault();
			toggleDebugPanel();
		}
	}

	// Set up keyboard listener
	onMounted(() => {
		window.addEventListener("keydown", handleKeyPress);
	});

	onUnmounted(() => {
		window.removeEventListener("keydown", handleKeyPress);
	});

	return {
		showDebugPanel,
		toggleDebugPanel,
		openDebugPanel,
		closeDebugPanel
	};
}
