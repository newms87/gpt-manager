<template>
	<div v-if="isCapturing" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
		<div class="bg-white rounded-lg shadow-lg p-6 flex flex-col items-center">
			<QSpinner color="sky" size="lg" class="mb-4" />
			<span class="text-slate-700">Capturing screenshot...</span>
		</div>
	</div>
</template>

<script setup lang="ts">
import { ref, watch } from "vue";

const props = withDefaults(defineProps<{
	targetRef: HTMLElement | null;
	requestId?: string | null;
	autoCapture?: boolean;
}>(), {
	requestId: null,
	autoCapture: false
});

const emit = defineEmits<{
	captured: [file: File];
	error: [error: Error];
}>();

const isCapturing = ref(false);

/**
 * Dynamically import html2canvas
 * Returns null if not available
 * NOTE: Uses string variable to prevent Rollup from trying to resolve at build time
 */
async function loadHtml2Canvas(): Promise<{ default: (element: HTMLElement, options?: object) => Promise<HTMLCanvasElement> } | null> {
	try {
		// Use a variable to prevent static analysis from resolving the import
		const moduleName = "html2canvas";
		const module = await import(/* @vite-ignore */ moduleName);
		return module;
	} catch (e) {
		console.error("html2canvas not installed. Run: yarn add html2canvas");
		return null;
	}
}

/**
 * Capture screenshot of the target element
 */
async function captureScreenshot() {
	if (!props.targetRef) {
		emit("error", new Error("No target element provided"));
		return;
	}

	isCapturing.value = true;

	try {
		const html2canvasModule = await loadHtml2Canvas();
		if (!html2canvasModule) {
			throw new Error("html2canvas library not available. Install with: yarn add html2canvas");
		}

		const html2canvas = html2canvasModule.default;

		// Capture the element
		const canvas = await html2canvas(props.targetRef, {
			backgroundColor: "#f8fafc", // slate-50
			scale: 2, // Higher resolution
			logging: false,
			useCORS: true,
			allowTaint: true
		});

		// Convert canvas to blob
		const blob = await new Promise<Blob | null>((resolve) => {
			canvas.toBlob(resolve, "image/png", 0.95);
		});

		if (!blob) {
			throw new Error("Failed to create image blob");
		}

		// Create file from blob
		const fileName = `screenshot-${Date.now()}.png`;
		const file = new File([blob], fileName, { type: "image/png" });

		emit("captured", file);
	} catch (error) {
		console.error("Screenshot capture failed:", error);
		emit("error", error instanceof Error ? error : new Error(String(error)));
	} finally {
		isCapturing.value = false;
	}
}

/**
 * Watch for requestId changes to auto-capture
 */
watch(
	() => props.requestId,
	(newRequestId) => {
		if (props.autoCapture && newRequestId && props.targetRef) {
			captureScreenshot();
		}
	}
);

/**
 * Expose capture method for manual triggering
 */
defineExpose({
	captureScreenshot
});
</script>
