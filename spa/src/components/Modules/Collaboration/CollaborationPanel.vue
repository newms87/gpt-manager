<template>
	<div class="flex h-full overflow-hidden bg-slate-100">
		<!-- Chat sidebar -->
		<div
			class="flex flex-col overflow-hidden border-r border-slate-300 bg-white shadow-sm"
			:style="{ width: sidebarWidth + 'px' }"
		>
			<!-- Chat fills the sidebar -->
			<CollaborationChat
				:thread="thread"
				:loading="loading"
				:readonly="readonly"
				class="flex-grow overflow-hidden"
				@send-message="$emit('send-message', $event)"
				@screenshot-needed="$emit('screenshot-needed', $event)"
			/>
		</div>

		<!-- Resize handle -->
		<div
			class="w-1 cursor-col-resize bg-slate-300 hover:bg-sky-500 transition-colors flex-shrink-0"
			@mousedown="startResize"
		/>

		<!-- Preview area -->
		<div class="flex-grow flex flex-col overflow-hidden bg-slate-100">
			<!-- Preview content - fills the entire area -->
			<div class="flex-grow overflow-hidden p-4">
				<slot name="preview">
					<div class="flex items-center justify-center h-full text-slate-500">
						No preview content
					</div>
				</slot>
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import CollaborationChat from "@/components/Modules/Collaboration/CollaborationChat.vue";
import { CollaborationThread, SendMessagePayload } from "@/components/Modules/Collaboration/types";
import { getItem, setItem } from "quasar-ui-danx";
import { onMounted, onUnmounted, ref } from "vue";

const SIDEBAR_WIDTH_KEY = "collaboration-sidebar-width";
const DEFAULT_SIDEBAR_WIDTH = 400;
const MIN_SIDEBAR_WIDTH = 300;
const MAX_SIDEBAR_WIDTH = 800;

withDefaults(defineProps<{
	thread: CollaborationThread;
	loading?: boolean;
	readonly?: boolean;
}>(), {
	loading: false,
	readonly: false
});

defineEmits<{
	"send-message": [payload: SendMessagePayload];
	"screenshot-needed": [requestId: string];
}>();

// Sidebar width state with localStorage persistence
const sidebarWidth = ref(DEFAULT_SIDEBAR_WIDTH);
const isResizing = ref(false);

/**
 * Load saved sidebar width from localStorage
 */
function loadSidebarWidth() {
	const saved = getItem(SIDEBAR_WIDTH_KEY);
	if (saved && typeof saved === "number") {
		sidebarWidth.value = Math.max(MIN_SIDEBAR_WIDTH, Math.min(MAX_SIDEBAR_WIDTH, saved));
	}
}

/**
 * Save sidebar width to localStorage
 */
function saveSidebarWidth() {
	setItem(SIDEBAR_WIDTH_KEY, sidebarWidth.value);
}

/**
 * Start resize operation
 */
function startResize(event: MouseEvent) {
	event.preventDefault();
	isResizing.value = true;
	document.addEventListener("mousemove", onResize);
	document.addEventListener("mouseup", stopResize);
}

/**
 * Handle resize mouse movement
 */
function onResize(event: MouseEvent) {
	if (!isResizing.value) return;

	const newWidth = event.clientX;
	sidebarWidth.value = Math.max(MIN_SIDEBAR_WIDTH, Math.min(MAX_SIDEBAR_WIDTH, newWidth));
}

/**
 * Stop resize operation and save
 */
function stopResize() {
	isResizing.value = false;
	document.removeEventListener("mousemove", onResize);
	document.removeEventListener("mouseup", stopResize);
	saveSidebarWidth();
}

onMounted(() => {
	loadSidebarWidth();
});

onUnmounted(() => {
	document.removeEventListener("mousemove", onResize);
	document.removeEventListener("mouseup", stopResize);
});
</script>
